<?php
// Powerloom Order Tracker - JSON API
//
// GET  api.php?action=orders&status=Weaving&payment=Unpaid&q=noor
// GET  api.php?action=summary
// GET  api.php?action=invoice&order_no=PL-1043
// POST api.php?action=add_order      (JSON body)
// POST api.php?action=mark_paid      (JSON body: invoice_no)

header('Content-Type: application/json');
require __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';

function body() {
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

try {
    if ($action === 'orders') {
        $sql = 'SELECT * FROM v_order_billing WHERE 1 = 1';
        $args = [];

        if (!empty($_GET['status'])) {
            $sql .= ' AND status = :status';
            $args[':status'] = $_GET['status'];
        }
        if (!empty($_GET['payment'])) {
            $sql .= ' AND payment_status = :payment';
            $args[':payment'] = $_GET['payment'];
        }
        if (!empty($_GET['q'])) {
            $sql .= ' AND (customer LIKE :q OR order_no LIKE :q OR fabric LIKE :q)';
            $args[':q'] = '%' . $_GET['q'] . '%';
        }
        $sql .= ' ORDER BY delivery_date DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'summary') {
        $sql = "SELECT
                  SUM(status <> 'Delivered')                            AS open_orders,
                  SUM(CASE WHEN status <> 'Delivered' THEN meters END)  AS meters_open,
                  SUM(amount)                                           AS billed,
                  SUM(CASE WHEN payment_status = 'Unpaid' THEN total END) AS due
                FROM v_order_billing";
        echo json_encode($pdo->query($sql)->fetch());
        exit;
    }

    if ($action === 'invoice') {
        $stmt = $pdo->prepare('SELECT * FROM v_order_billing WHERE order_no = :no');
        $stmt->execute([':no' => $_GET['order_no'] ?? '']);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found']);
            exit;
        }
        echo json_encode($row);
        exit;
    }

    if ($action === 'add_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = body();
        foreach (['customer', 'fabric', 'meters', 'rate', 'delivery_date'] as $f) {
            if (empty($in[$f])) {
                http_response_code(422);
                echo json_encode(['error' => "Missing field: $f"]);
                exit;
            }
        }

        $pdo->beginTransaction();

        // Reuse the customer and fabric if they already exist.
        $pdo->prepare('INSERT IGNORE INTO customers (name) VALUES (:n)')->execute([':n' => $in['customer']]);
        $pdo->prepare('INSERT IGNORE INTO fabrics (name) VALUES (:n)')->execute([':n' => $in['fabric']]);

        $cid = $pdo->prepare('SELECT id FROM customers WHERE name = :n');
        $cid->execute([':n' => $in['customer']]);
        $customerId = $cid->fetchColumn();

        $fid = $pdo->prepare('SELECT id FROM fabrics WHERE name = :n');
        $fid->execute([':n' => $in['fabric']]);
        $fabricId = $fid->fetchColumn();

        $next = (int) $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(order_no, 4) AS UNSIGNED)), 1040) + 1 FROM orders")->fetchColumn();
        $orderNo = 'PL-' . $next;

        $ins = $pdo->prepare(
            'INSERT INTO orders (order_no, customer_id, fabric_id, meters, rate_per_m, status, delivery_date)
             VALUES (:no, :cid, :fid, :m, :r, :s, :d)'
        );
        $ins->execute([
            ':no'  => $orderNo,
            ':cid' => $customerId,
            ':fid' => $fabricId,
            ':m'   => $in['meters'],
            ':r'   => $in['rate'],
            ':s'   => $in['status'] ?? 'Pending',
            ':d'   => $in['delivery_date'],
        ]);

        $orderId = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO invoices (order_id, invoice_no, issued_on) VALUES (:oid, :inv, CURDATE())')
            ->execute([':oid' => $orderId, ':inv' => 'INV-' . (2000 + (int) $orderId)]);

        $pdo->commit();
        echo json_encode(['ok' => true, 'order_no' => $orderNo]);
        exit;
    }

    if ($action === 'mark_paid' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = body();
        $stmt = $pdo->prepare('UPDATE invoices SET paid_on = CURDATE() WHERE invoice_no = :inv AND paid_on IS NULL');
        $stmt->execute([':inv' => $in['invoice_no'] ?? '']);
        echo json_encode(['ok' => true, 'updated' => $stmt->rowCount()]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Query failed']);
}
