<?php

declare(strict_types=1);

$pdo = require __DIR__ . '/../app/db.php';
$config = require __DIR__ . '/../app/config.php';

$currency = $config['currency'];
$currencyCode = $config['currency_code'];
$page = $_GET['page'] ?? 'dashboard';
$alert = '';

function fetchAll(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function execute(PDO $pdo, string $sql, array $params = []): void
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create_branch') {
        execute(
            $pdo,
            'INSERT INTO branches (name, address, phone, delivery_enabled, delivery_zone, delivery_fee, currency)
             VALUES (:name, :address, :phone, :delivery_enabled, :delivery_zone, :delivery_fee, :currency)',
            [
                ':name' => trim($_POST['name'] ?? ''),
                ':address' => trim($_POST['address'] ?? ''),
                ':phone' => trim($_POST['phone'] ?? ''),
                ':delivery_enabled' => isset($_POST['delivery_enabled']) ? 1 : 0,
                ':delivery_zone' => trim($_POST['delivery_zone'] ?? ''),
                ':delivery_fee' => (float) ($_POST['delivery_fee'] ?? 0),
                ':currency' => $currency,
            ]
        );
        $alert = 'تمت إضافة الفرع بنجاح.';
        $page = 'branches';
    }

    if (isset($_POST['action']) && $_POST['action'] === 'create_product') {
        execute(
            $pdo,
            'INSERT INTO products (branch_id, name, sku, barcode, price, is_active)
             VALUES (:branch_id, :name, :sku, :barcode, :price, :is_active)',
            [
                ':branch_id' => (int) ($_POST['branch_id'] ?? 0),
                ':name' => trim($_POST['name'] ?? ''),
                ':sku' => trim($_POST['sku'] ?? ''),
                ':barcode' => trim($_POST['barcode'] ?? ''),
                ':price' => (float) ($_POST['price'] ?? 0),
                ':is_active' => isset($_POST['is_active']) ? 1 : 0,
            ]
        );
        $alert = 'تمت إضافة المنتج بنجاح.';
        $page = 'products';
    }

    if (isset($_POST['action']) && $_POST['action'] === 'create_user') {
        execute(
            $pdo,
            'INSERT INTO users (branch_id, username, full_name, role, is_active)
             VALUES (:branch_id, :username, :full_name, :role, :is_active)',
            [
                ':branch_id' => (int) ($_POST['branch_id'] ?? 0),
                ':username' => trim($_POST['username'] ?? ''),
                ':full_name' => trim($_POST['full_name'] ?? ''),
                ':role' => trim($_POST['role'] ?? ''),
                ':is_active' => isset($_POST['is_active']) ? 1 : 0,
            ]
        );
        $alert = 'تمت إضافة المستخدم بنجاح.';
        $page = 'users';
    }

    if (isset($_POST['action']) && $_POST['action'] === 'create_sale') {
        $branchId = (int) ($_POST['branch_id'] ?? 0);
        $cashierId = (int) ($_POST['cashier_id'] ?? 0);
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 1);

        $product = fetchAll($pdo, 'SELECT price FROM products WHERE id = :id', [':id' => $productId]);
        $price = $product[0]['price'] ?? 0;
        $lineTotal = $quantity * (float) $price;

        execute(
            $pdo,
            'INSERT INTO sales (branch_id, cashier_id, total_amount, currency, created_at, notes)
             VALUES (:branch_id, :cashier_id, :total_amount, :currency, :created_at, :notes)',
            [
                ':branch_id' => $branchId,
                ':cashier_id' => $cashierId,
                ':total_amount' => $lineTotal,
                ':currency' => $currencyCode,
                ':created_at' => date('c'),
                ':notes' => trim($_POST['notes'] ?? ''),
            ]
        );

        $saleId = (int) $pdo->lastInsertId();
        execute(
            $pdo,
            'INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, line_total)
             VALUES (:sale_id, :product_id, :quantity, :unit_price, :line_total)',
            [
                ':sale_id' => $saleId,
                ':product_id' => $productId,
                ':quantity' => $quantity,
                ':unit_price' => $price,
                ':line_total' => $lineTotal,
            ]
        );

        $alert = 'تم تسجيل عملية البيع.';
        $page = 'sales';
    }
}

$branches = fetchAll($pdo, 'SELECT * FROM branches ORDER BY id DESC');
$products = fetchAll($pdo, 'SELECT products.*, branches.name AS branch_name FROM products JOIN branches ON products.branch_id = branches.id ORDER BY products.id DESC');
$users = fetchAll($pdo, 'SELECT users.*, branches.name AS branch_name FROM users JOIN branches ON users.branch_id = branches.id ORDER BY users.id DESC');
$sales = fetchAll(
    $pdo,
    'SELECT sales.*, branches.name AS branch_name, users.full_name AS cashier_name
     FROM sales
     JOIN branches ON sales.branch_id = branches.id
     JOIN users ON sales.cashier_id = users.id
     ORDER BY sales.id DESC'
);
$branchSales = fetchAll(
    $pdo,
    'SELECT branches.id, branches.name, COUNT(sales.id) AS transactions, COALESCE(SUM(sales.total_amount), 0) AS total_sales
     FROM branches
     LEFT JOIN sales ON branches.id = sales.branch_id
     GROUP BY branches.id
     ORDER BY branches.id'
);

?><!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>نظام كاشير متعدد الفروع</title>
    <link rel="stylesheet" href="../assets/styles.css" />
</head>
<body>
<header>
    <h1>نظام كاشير متعدد الفروع</h1>
    <p>واجهة تشغيلية وإدارية مبسطة - العملة الأساسية: <?= htmlspecialchars($currency) ?></p>
</header>
<div class="container">
    <nav>
        <a href="?page=dashboard">لوحة التحكم</a>
        <a href="?page=branches">الفروع</a>
        <a href="?page=products">المنتجات</a>
        <a href="?page=users">المستخدمون</a>
        <a href="?page=sales">المبيعات</a>
    </nav>

    <?php if ($alert): ?>
        <div class="alert"><?= htmlspecialchars($alert) ?></div>
    <?php endif; ?>

    <?php if ($page === 'dashboard'): ?>
        <section>
            <h2>ملخص المبيعات حسب الفروع</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>الفرع</th>
                        <th>عدد العمليات</th>
                        <th>إجمالي المبيعات (<?= htmlspecialchars($currencyCode) ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branchSales as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= (int) $row['transactions'] ?></td>
                            <td><?= number_format((float) $row['total_sales'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <?php if ($page === 'branches'): ?>
        <section>
            <h2>إضافة فرع جديد</h2>
            <form method="post">
                <input type="hidden" name="action" value="create_branch" />
                <label>اسم الفرع</label>
                <input name="name" required />
                <label>العنوان</label>
                <input name="address" required />
                <label>رقم الهاتف</label>
                <input name="phone" required />
                <label>منطقة التوصيل</label>
                <input name="delivery_zone" />
                <label>رسوم التوصيل</label>
                <input name="delivery_fee" type="number" step="0.01" />
                <label>
                    <input type="checkbox" name="delivery_enabled" /> تفعيل التوصيل
                </label>
                <button type="submit">حفظ الفرع</button>
            </form>
        </section>
        <section>
            <h2>قائمة الفروع</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>الهاتف</th>
                        <th>التوصيل</th>
                        <th>العملة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $branch): ?>
                        <tr>
                            <td><?= htmlspecialchars($branch['name']) ?></td>
                            <td><?= htmlspecialchars($branch['phone']) ?></td>
                            <td>
                                <?= $branch['delivery_enabled'] ? 'مفعل' : 'غير مفعل' ?>
                                <span class="badge"><?= htmlspecialchars($branch['delivery_zone']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($branch['currency']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <?php if ($page === 'products'): ?>
        <section>
            <h2>إضافة منتج</h2>
            <form method="post">
                <input type="hidden" name="action" value="create_product" />
                <label>الفرع</label>
                <select name="branch_id" required>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>اسم المنتج</label>
                <input name="name" required />
                <label>السعر</label>
                <input name="price" type="number" step="0.01" required />
                <label>SKU</label>
                <input name="sku" />
                <label>الباركود</label>
                <input name="barcode" />
                <label>
                    <input type="checkbox" name="is_active" checked /> نشط
                </label>
                <button type="submit">حفظ المنتج</button>
            </form>
        </section>
        <section>
            <h2>قائمة المنتجات</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>الفرع</th>
                        <th>المنتج</th>
                        <th>السعر</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= htmlspecialchars($product['branch_name']) ?></td>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td><?= number_format((float) $product['price'], 2) ?></td>
                            <td><?= $product['is_active'] ? 'نشط' : 'موقوف' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <?php if ($page === 'users'): ?>
        <section>
            <h2>إضافة مستخدم</h2>
            <form method="post">
                <input type="hidden" name="action" value="create_user" />
                <label>الفرع</label>
                <select name="branch_id" required>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>اسم المستخدم</label>
                <input name="username" required />
                <label>الاسم الكامل</label>
                <input name="full_name" required />
                <label>الدور</label>
                <input name="role" required placeholder="مدير فرع / كاشير" />
                <label>
                    <input type="checkbox" name="is_active" checked /> نشط
                </label>
                <button type="submit">حفظ المستخدم</button>
            </form>
        </section>
        <section>
            <h2>قائمة المستخدمين</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>الفرع</th>
                        <th>الاسم</th>
                        <th>الدور</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['branch_name']) ?></td>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                            <td><?= htmlspecialchars($user['role']) ?></td>
                            <td><?= $user['is_active'] ? 'نشط' : 'موقوف' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <?php if ($page === 'sales'): ?>
        <section>
            <h2>تسجيل عملية بيع</h2>
            <form method="post">
                <input type="hidden" name="action" value="create_sale" />
                <label>الفرع</label>
                <select name="branch_id" required>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>الكاشير</label>
                <select name="cashier_id" required>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= (int) $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>المنتج</label>
                <select name="product_id" required>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>الكمية</label>
                <input name="quantity" type="number" value="1" min="1" />
                <label>ملاحظات</label>
                <textarea name="notes"></textarea>
                <button type="submit">حفظ عملية البيع</button>
            </form>
        </section>
        <section>
            <h2>سجل المبيعات</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>الفرع</th>
                        <th>الكاشير</th>
                        <th>الإجمالي</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td><?= htmlspecialchars($sale['branch_name']) ?></td>
                            <td><?= htmlspecialchars($sale['cashier_name']) ?></td>
                            <td><?= number_format((float) $sale['total_amount'], 2) ?> <?= htmlspecialchars($sale['currency']) ?></td>
                            <td><?= htmlspecialchars($sale['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</div>
</body>
</html>
