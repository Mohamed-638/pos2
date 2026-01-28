<?php

declare(strict_types=1);

session_start();

$pdo = require __DIR__ . '/../app/db.php';
$config = require __DIR__ . '/../app/config.php';

$currency = $config['currency'];
$currencyCode = $config['currency_code'];
$page = $_GET['page'] ?? 'dashboard';
$alert = $_SESSION['alert'] ?? '';
$alertType = $_SESSION['alert_type'] ?? 'success';

unset($_SESSION['alert'], $_SESSION['alert_type']);

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$csrfToken = $_SESSION['csrf_token'];

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

function redirectWithMessage(string $page, string $message, string $type = 'success'): void
{
    $_SESSION['alert'] = $message;
    $_SESSION['alert_type'] = $type;
    header('Location: ?page=' . urlencode($page));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        redirectWithMessage($page, 'رمز الأمان غير صالح، يرجى المحاولة مرة أخرى.', 'error');
    }

    if (isset($_POST['action']) && $_POST['action'] === 'create_branch') {
        execute(
            $pdo,
            'INSERT INTO branches (name, address, phone, work_hours, delivery_enabled, delivery_zone, delivery_fee, delivery_schedule, currency)
             VALUES (:name, :address, :phone, :work_hours, :delivery_enabled, :delivery_zone, :delivery_fee, :delivery_schedule, :currency)',
            [
                ':name' => trim($_POST['name'] ?? ''),
                ':address' => trim($_POST['address'] ?? ''),
                ':phone' => trim($_POST['phone'] ?? ''),
                ':work_hours' => trim($_POST['work_hours'] ?? ''),
                ':delivery_enabled' => isset($_POST['delivery_enabled']) ? 1 : 0,
                ':delivery_zone' => trim($_POST['delivery_zone'] ?? ''),
                ':delivery_fee' => (float) ($_POST['delivery_fee'] ?? 0),
                ':delivery_schedule' => trim($_POST['delivery_schedule'] ?? ''),
                ':currency' => $currency,
            ]
        );
        redirectWithMessage('branches', 'تمت إضافة الفرع بنجاح.');
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
        redirectWithMessage('products', 'تمت إضافة المنتج بنجاح.');
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
        redirectWithMessage('users', 'تمت إضافة المستخدم بنجاح.');
    }

    if (isset($_POST['action']) && $_POST['action'] === 'create_sale') {
        $branchId = (int) ($_POST['branch_id'] ?? 0);
        $cashierId = (int) ($_POST['cashier_id'] ?? 0);
        $productIds = array_map('intval', $_POST['product_id'] ?? []);
        $quantities = array_map('intval', $_POST['quantity'] ?? []);
        $discount = (float) ($_POST['discount'] ?? 0);
        $paymentMethod = trim($_POST['payment_method'] ?? 'cash');
        $amountPaid = (float) ($_POST['amount_paid'] ?? 0);

        if (count($productIds) === 0) {
            redirectWithMessage('sales', 'يرجى إضافة عنصر واحد على الأقل.', 'error');
        }

        $totalAmount = 0.0;
        $items = [];
        foreach ($productIds as $index => $productId) {
            if ($productId <= 0) {
                continue;
            }
            $quantity = $quantities[$index] ?? 1;
            $product = fetchAll(
                $pdo,
                'SELECT price FROM products WHERE id = :id',
                [':id' => $productId]
            );
            $price = (float) ($product[0]['price'] ?? 0);
            $lineTotal = $price * $quantity;
            $totalAmount += $lineTotal;
            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $price,
                'line_total' => $lineTotal,
            ];
        }

        $totalAmount = max(0, $totalAmount - $discount);
        $changeDue = max(0, $amountPaid - $totalAmount);

        execute(
            $pdo,
            'INSERT INTO sales (branch_id, cashier_id, total_amount, discount, payment_method, amount_paid, change_due, currency, created_at, notes)
             VALUES (:branch_id, :cashier_id, :total_amount, :discount, :payment_method, :amount_paid, :change_due, :currency, :created_at, :notes)',
            [
                ':branch_id' => $branchId,
                ':cashier_id' => $cashierId,
                ':total_amount' => $totalAmount,
                ':discount' => $discount,
                ':payment_method' => $paymentMethod,
                ':amount_paid' => $amountPaid,
                ':change_due' => $changeDue,
                ':currency' => $currencyCode,
                ':created_at' => date('c'),
                ':notes' => trim($_POST['notes'] ?? ''),
            ]
        );

        $saleId = (int) $pdo->lastInsertId();
        foreach ($items as $item) {
            execute(
                $pdo,
                'INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, line_total)
                 VALUES (:sale_id, :product_id, :quantity, :unit_price, :line_total)',
                [
                    ':sale_id' => $saleId,
                    ':product_id' => $item['product_id'],
                    ':quantity' => $item['quantity'],
                    ':unit_price' => $item['unit_price'],
                    ':line_total' => $item['line_total'],
                ]
            );
        }

        redirectWithMessage('sales', 'تم تسجيل عملية البيع.');
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

$productsByBranch = fetchAll(
    $pdo,
    'SELECT products.*, branches.name AS branch_name FROM products JOIN branches ON products.branch_id = branches.id WHERE products.is_active = 1 ORDER BY products.id DESC'
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
        <a href="?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">لوحة التحكم</a>
        <a href="?page=branches" class="<?= $page === 'branches' ? 'active' : '' ?>">الفروع</a>
        <a href="?page=products" class="<?= $page === 'products' ? 'active' : '' ?>">المنتجات</a>
        <a href="?page=users" class="<?= $page === 'users' ? 'active' : '' ?>">المستخدمون</a>
        <a href="?page=sales" class="<?= $page === 'sales' ? 'active' : '' ?>">المبيعات</a>
    </nav>

    <?php if ($alert): ?>
        <div class="alert <?= $alertType === 'error' ? 'error' : '' ?>"><?= htmlspecialchars($alert) ?></div>
    <?php endif; ?>

    <?php if ($page === 'dashboard'): ?>
        <section>
            <h2>ملخص المبيعات حسب الفروع</h2>
            <div class="cards">
                <div class="card">
                    <h3>عدد الفروع</h3>
                    <p><?= count($branches) ?></p>
                </div>
                <div class="card">
                    <h3>عدد المنتجات النشطة</h3>
                    <p><?= count(array_filter($products, fn ($product) => (int) $product['is_active'] === 1)) ?></p>
                </div>
                <div class="card">
                    <h3>عدد المستخدمين</h3>
                    <p><?= count($users) ?></p>
                </div>
            </div>
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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>" />
                <label>اسم الفرع</label>
                <input name="name" required />
                <label>العنوان</label>
                <input name="address" required />
                <label>رقم الهاتف</label>
                <input name="phone" required />
                <label>ساعات العمل</label>
                <input name="work_hours" placeholder="مثال: 9 صباحًا - 10 مساءً" />
                <label>منطقة التوصيل</label>
                <input name="delivery_zone" />
                <label>رسوم التوصيل</label>
                <input name="delivery_fee" type="number" step="0.01" />
                <label>جدول التوصيل</label>
                <input name="delivery_schedule" placeholder="مثال: يوميًا 12-8" />
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
                        <th>ساعات العمل</th>
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
                            <td><?= htmlspecialchars($branch['work_hours']) ?></td>
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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>" />
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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>" />
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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>" />
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
                <label>عناصر الفاتورة</label>
                <table class="table items-table" id="items-table">
                    <thead>
                        <tr>
                            <th>المنتج</th>
                            <th>الكمية</th>
                        </tr>
                    </thead>
                    <tbody id="items-body">
                        <tr>
                            <td>
                                <select name="product_id[]" required>
                                    <?php foreach ($productsByBranch as $product): ?>
                                        <option value="<?= (int) $product['id'] ?>">
                                            <?= htmlspecialchars($product['name']) ?> - <?= number_format((float) $product['price'], 2) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input name="quantity[]" type="number" value="1" min="1" />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <button class="button-secondary" type="button" id="add-item">إضافة عنصر</button>
                <label>الخصم</label>
                <input name="discount" type="number" step="0.01" value="0" />
                <label>طريقة الدفع</label>
                <select name="payment_method">
                    <option value="cash">نقدي</option>
                    <option value="card">بطاقة</option>
                    <option value="wallet">محفظة</option>
                    <option value="transfer">تحويل</option>
                </select>
                <label>المبلغ المدفوع</label>
                <input name="amount_paid" type="number" step="0.01" value="0" />
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
                        <th>الخصم</th>
                        <th>طريقة الدفع</th>
                        <th>الإجمالي</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td><?= htmlspecialchars($sale['branch_name']) ?></td>
                            <td><?= htmlspecialchars($sale['cashier_name']) ?></td>
                            <td><?= number_format((float) $sale['discount'], 2) ?></td>
                            <td><?= htmlspecialchars($sale['payment_method']) ?></td>
                            <td><?= number_format((float) $sale['total_amount'], 2) ?> <?= htmlspecialchars($sale['currency']) ?></td>
                            <td><?= htmlspecialchars($sale['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</div>
<script>
const addItemButton = document.getElementById('add-item');
if (addItemButton) {
  addItemButton.addEventListener('click', () => {
    const tbody = document.getElementById('items-body');
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>
        <select name="product_id[]" required>
          <?php foreach ($productsByBranch as $product): ?>
            <option value="<?= (int) $product['id'] ?>">
              <?= htmlspecialchars($product['name']) ?> - <?= number_format((float) $product['price'], 2) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </td>
      <td>
        <input name="quantity[]" type="number" value="1" min="1" />
      </td>
    `;
    tbody.appendChild(row);
  });
}
</script>
</body>
</html>
