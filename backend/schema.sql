-- Powerloom Order and Billing Tracker
-- MySQL schema

CREATE DATABASE IF NOT EXISTS powerloom
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE powerloom;

CREATE TABLE customers (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  phone       VARCHAR(20),
  city        VARCHAR(80) DEFAULT 'Malegaon',
  gstin       VARCHAR(20),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_name (name)
) ENGINE=InnoDB;

CREATE TABLE fabrics (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  width_inch  DECIMAL(5,1),
  UNIQUE KEY uq_fabric_name (name)
) ENGINE=InnoDB;

CREATE TABLE orders (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  order_no      VARCHAR(20) NOT NULL,
  customer_id   INT NOT NULL,
  fabric_id     INT NOT NULL,
  meters        DECIMAL(10,2) NOT NULL,
  rate_per_m    DECIMAL(8,2) NOT NULL,
  status        ENUM('Pending','Weaving','Ready','Delivered') NOT NULL DEFAULT 'Pending',
  delivery_date DATE,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order_no (order_no),
  KEY idx_status (status),
  KEY idx_customer (customer_id),
  CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_order_fabric   FOREIGN KEY (fabric_id)   REFERENCES fabrics(id)
) ENGINE=InnoDB;

CREATE TABLE invoices (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  order_id    INT NOT NULL,
  invoice_no  VARCHAR(20) NOT NULL,
  gst_percent DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  issued_on   DATE NOT NULL,
  paid_on     DATE DEFAULT NULL,
  UNIQUE KEY uq_invoice_no (invoice_no),
  KEY idx_paid (paid_on),
  CONSTRAINT fk_invoice_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Order value with GST, used by the orders list and the dashboard
CREATE OR REPLACE VIEW v_order_billing AS
SELECT
  o.id,
  o.order_no,
  c.name                              AS customer,
  f.name                              AS fabric,
  o.meters,
  o.rate_per_m,
  o.status,
  o.delivery_date,
  ROUND(o.meters * o.rate_per_m, 2)   AS amount,
  ROUND(o.meters * o.rate_per_m * COALESCE(i.gst_percent, 5) / 100, 2) AS gst,
  ROUND(o.meters * o.rate_per_m * (1 + COALESCE(i.gst_percent, 5) / 100), 2) AS total,
  i.invoice_no,
  CASE WHEN i.paid_on IS NULL THEN 'Unpaid' ELSE 'Paid' END AS payment_status
FROM orders o
JOIN customers c ON c.id = o.customer_id
JOIN fabrics   f ON f.id = o.fabric_id
LEFT JOIN invoices i ON i.order_id = o.id;

-- Sample data
INSERT INTO customers (name, phone) VALUES
  ('Shaikh Textiles', '9890000001'),
  ('Ansari Fabrics',  '9890000002'),
  ('Noor Trading',    '9890000003'),
  ('Khan Handloom',   '9890000004');

INSERT INTO fabrics (name, width_inch) VALUES
  ('Grey poplin', 44.0),
  ('Cotton shirting', 58.0),
  ('Sheeting 63in', 63.0);

INSERT INTO orders (order_no, customer_id, fabric_id, meters, rate_per_m, status, delivery_date) VALUES
  ('PL-1041', 1, 1, 4200, 41.50, 'Delivered', '2026-09-12'),
  ('PL-1042', 2, 2, 2600, 53.00, 'Delivered', '2026-09-18'),
  ('PL-1043', 3, 1, 6000, 40.00, 'Weaving',   '2026-10-04'),
  ('PL-1044', 1, 3, 3200, 47.50, 'Ready',     '2026-09-30');

INSERT INTO invoices (order_id, invoice_no, issued_on, paid_on) VALUES
  (1, 'INV-2041', '2026-09-12', '2026-09-20'),
  (2, 'INV-2042', '2026-09-18', NULL);
