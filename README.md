# Powerloom Order and Billing Tracker

Order and billing software for a powerloom unit. Tracks an order from the loom to delivery, works out what each customer still owes, and prints an invoice with GST.

**Live demo:** https://asra-iram.github.io/powerloom-order-tracker/

## Why I built it

Malegaon runs on powerloom and textile units, and most of the smaller ones still track orders in a notebook. The information they actually need is simple: which orders are still on the loom, how many meters are in production, and who has not paid yet. That is what this does.

## What it does

- Lists every order with customer, fabric, meters, rate and amount
- Filters by order status (Pending, Weaving, Ready, Delivered) and by payment status
- Searches across customer, order number and fabric
- Shows a summary at the top: open orders, meters in production, total billed and payment pending
- Generates an invoice for any order, with GST at five percent
- Adds new orders, and exports the current list as CSV

## Two versions in this repository

**The live demo** runs entirely in the browser, with sample data, so it can be hosted on GitHub Pages with no server. That is `index.html`.

**The real version** is PHP with MySQL, in the `backend` folder:

- `schema.sql` - the database: customers, fabrics, orders and invoices, with foreign keys and a `v_order_billing` view that computes amount, GST and payment status in one place
- `api.php` - JSON endpoints for listing orders, the dashboard summary, fetching an invoice, adding an order and marking an invoice paid
- `db.php` - PDO connection
- `config.sample.php` - copy to `config.php` and fill in credentials

Every query uses prepared statements with bound parameters, so user input never reaches the SQL string. Adding an order runs inside a transaction, because it touches customers, fabrics, orders and invoices together and a half written order is worse than none.

## Setting up the PHP version

```
mysql -u root -p < backend/schema.sql
cp backend/config.sample.php backend/config.php
# edit config.php with your database user and password
php -S localhost:8000
```

Then call the API, for example:

```
GET  /backend/api.php?action=orders&status=Weaving
GET  /backend/api.php?action=summary
POST /backend/api.php?action=add_order
```

## Built with

HTML5, CSS3 and plain JavaScript on the front end. PHP 8 with PDO and MySQL on the back end. No framework.

## Author

Asra Iram - [portfolio](https://asra-iram.github.io) - [GitHub](https://github.com/asra-iram)
