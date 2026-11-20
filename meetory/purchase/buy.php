<?php
// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 共通処理・DB接続を読み込む
require_once '../../meetory-functions.php';

$pdo = getPDO();

// ログイン確認
if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: ../user/login.php');
    exit;
}

// 購入制限の確認
if (!canPurchase($pdo, (int) $_SESSION['user_id'])) {
    $suspension = isAccountSuspended($pdo, (int) $_SESSION['user_id']);
    if ($suspension !== null) {
        exit('アカウント停止中のため購入できません。');
    }
    exit('購入制限が適用されているため購入できません。');
}

// URLから商品IDを取得
$goods_id = filter_input(INPUT_GET, 'goods_id', FILTER_VALIDATE_INT);

if (!$goods_id || $goods_id <= 0) {
    exit('商品IDが正しくありません。');
}

// 商品情報と出品者名を取得
$stmt = $pdo->prepare(
    'SELECT
        g.goods_id,
        g.goods_name,
        g.description,
        g.price,
        g.seller_id,
        g.is_sold,
        u.user_name AS seller_name
    FROM goods AS g
    INNER JOIN users AS u
        ON g.seller_id = u.user_id
    WHERE g.goods_id = :goods_id'
);

$stmt->execute(['goods_id' => $goods_id]);
$goods = $stmt->fetch(PDO::FETCH_ASSOC);

// 商品が存在しない場合
if (!$goods) {
    exit('商品が見つかりません。');
}

// 自分が出品した商品は購入できない
if ((int) $_SESSION['user_id'] === (int) $goods['seller_id']) {
    exit('自分が出品した商品は購入できません。');
}

// CSRF対策用トークンを作成
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// 過去の配送先情報を初期値として取得（最新の購入取引から）
$initial_shipping = [
    'shipping_name' => '',
    'shipping_postal_code' => '',
    'shipping_prefecture' => '',
    'shipping_city' => '',
    'shipping_address_line' => '',
    'shipping_building' => '',
    'shipping_phone' => ''
];
$stmtInitial = $pdo->prepare(
    'SELECT
        shipping_name,
        shipping_postal_code,
        shipping_prefecture,
        shipping_city,
        shipping_address_line,
        shipping_building,
        shipping_phone
    FROM buy
    WHERE buyer_id = :buyer_id
    ORDER BY purchased_at DESC
    LIMIT 1'
);
$stmtInitial->execute(['buyer_id' => (int) $_SESSION['user_id']]);
$pastShipping = $stmtInitial->fetch(PDO::FETCH_ASSOC);
if ($pastShipping) {
    foreach ($initial_shipping as $key => $default) {
        $initial_shipping[$key] = $pastShipping[$key] ?? $default;
    }
}
?>

<?php require_once '../../meetory-header.php'; ?>

<h1>購入内容の確認</h1>

<h2><?= htmlspecialchars($goods['goods_name'], ENT_QUOTES, 'UTF-8') ?></h2>

<p>
    商品説明：
    <?= nl2br(htmlspecialchars($goods['description'], ENT_QUOTES, 'UTF-8')) ?>
</p>

<p>
    価格：
    <?= number_format((int) $goods['price']) ?>円
</p>

<p>
    出品者：
    <?= htmlspecialchars($goods['seller_name'], ENT_QUOTES, 'UTF-8') ?>
</p>

<p>上記の商品を購入しますか？</p>

<form action="buy-process.php" method="post">
    <input
        type="hidden"
        name="goods_id"
        value="<?= (int) $goods['goods_id'] ?>"
    >

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"
    >

    <h3>配送先情報</h3>

    <div class="form-group">
        <label for="address_id">登録済み住所</label><br>
        <select id="address_id" name="address_id">
            <option value="">新しく入力する</option>
<?php
$addrStmt = $pdo->prepare('SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC');
$addrStmt->execute([(int)$_SESSION['user_id']]);
$regAddrs = $addrStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($regAddrs as $ra): ?>
            <option value="<?= (int)$ra['address_id'] ?>" <?= $ra['is_default']?'selected':'' ?>
                data-name="<?= htmlspecialchars($ra['recipient_name'], ENT_QUOTES, 'UTF-8') ?>"
                data-postal="<?= htmlspecialchars($ra['postal_code'], ENT_QUOTES, 'UTF-8') ?>"
                data-pref="<?= htmlspecialchars($ra['prefecture'], ENT_QUOTES, 'UTF-8') ?>"
                data-city="<?= htmlspecialchars($ra['city'], ENT_QUOTES, 'UTF-8') ?>"
                data-line="<?= htmlspecialchars($ra['address_line'], ENT_QUOTES, 'UTF-8') ?>"
                data-building="<?= htmlspecialchars($ra['building'], ENT_QUOTES, 'UTF-8') ?>"
                data-phone="<?= htmlspecialchars($ra['phone'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($ra['recipient_name'].' '.$ra['prefecture'].$ra['city'].$ra['address_line'], ENT_QUOTES, 'UTF-8') ?>
            </option>
<?php endforeach; ?>
        </select>
    </div>


    <div class="form-group">
        <label for="shipping_name">配送先氏名</label><br>
        <input
            type="text"
            id="shipping_name"
            name="shipping_name"
            maxlength="100"
            required
            value="<?= htmlspecialchars($initial_shipping['shipping_name'], ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>

    <div class="form-group">
        <label for="shipping_postal_code">郵便番号</label><br>
        <input
            type="text"
            id="shipping_postal_code"
            name="shipping_postal_code"
            maxlength="20"
            required
            value="<?= htmlspecialchars($initial_shipping['shipping_postal_code'], ENT_QUOTES, 'UTF-8') ?>"
        > <button type="button" id="zipSearchBtnBuy">住所検索</button> <button type="button" id="zipRevBtnBuy">郵便番号検索</button>
    </div>

    <div class="form-group">
        <label for="shipping_prefecture">都道府県</label><br>
        <input
            type="text"
            id="shipping_prefecture"
            name="shipping_prefecture"
            maxlength="50"
            required
            value="<?= htmlspecialchars($initial_shipping['shipping_prefecture'], ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>

    <div class="form-group">
        <label for="shipping_city">市区町村</label><br>
        <input
            type="text"
            id="shipping_city"
            name="shipping_city"
            maxlength="100"
            required
            value="<?= htmlspecialchars($initial_shipping['shipping_city'], ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>

    <div class="form-group">
        <label for="shipping_address_line">町名・番地</label><br>
        <input
            type="text"
            id="shipping_address_line"
            name="shipping_address_line"
            maxlength="255"
            required
            value="<?= htmlspecialchars($initial_shipping['shipping_address_line'], ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>

    <div class="form-group">
        <label for="shipping_building">建物名・部屋番号（任意）</label><br>
        <input
            type="text"
            id="shipping_building"
            name="shipping_building"
            maxlength="255"
            value="<?= htmlspecialchars($initial_shipping['shipping_building'], ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>

    <div class="form-group">
        <label for="shipping_phone">電話番号</label><br>
        <input
            type="text"
            id="shipping_phone"
            name="shipping_phone"
            maxlength="20"
            required
            value="<?= htmlspecialchars($initial_shipping['shipping_phone'], ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>

    <h3>支払い方法</h3>

    <label for="payment_method">支払い方法を選択してください</label><br>

    <select id="payment_method" name="payment_method" required>
        <option value="">選択してください</option>
        <option value="銀行振込（テスト）">銀行振込（テスト）</option>
        <option value="その他（テスト）">その他（テスト）</option>
    </select>

    <p>※現在はテスト用のため、実際の決済や送金は行いません。</p>

    <button type="submit">購入を確定する</button>
</form>

<p>
    <a href="../goods/goods-detail.php?goods_id=<?= (int) $goods['goods_id'] ?>">
        商品詳細に戻る
    </a>
</p>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var revBtn = document.getElementById('zipRevBtnBuy');
  if (revBtn) {
    revBtn.addEventListener('click', function() {
      var pref = document.getElementById('shipping_prefecture').value;
      var city = document.getElementById('shipping_city').value;
      var line = document.getElementById('shipping_address_line').value;
      var q = encodeURIComponent((pref + ' ' + city + ' ' + line).trim());
      if (!q) { alert('住所を入力してください'); return; }
      revBtn.disabled = true; revBtn.textContent = '検索中...';
      fetch('https://zipcoda.net/api?address=' + q)
        .then(function(r){ if (!r.ok) throw new Error('HTTP'); return r.json(); })
        .then(function(d){
          if (!d.items || !d.items.length) { alert('該当郵便番号が見つかりません'); return; }
          if (d.items.length === 1) { document.getElementById('shipping_postal_code').value = d.items[0].zipcode; return; }
          var sel = document.createElement('select');
          d.items.slice(0, 20).forEach(function(it){ var o = document.createElement('option'); o.value = it.zipcode; o.textContent = it.zipcode + ' ' + it.address; sel.appendChild(o); });
          sel.onchange = function(){ document.getElementById('shipping_postal_code').value = sel.value; };
          revBtn.parentNode.appendChild(sel);
        })
        .catch(function(){ alert('APIに接続できませんでした'); })
        .finally(function(){ revBtn.disabled = false; revBtn.textContent = '郵便番号検索'; });
    });
  }
  var zipBtn = document.getElementById('zipSearchBtnBuy');
  if (zipBtn) {
    zipBtn.addEventListener('click', function() {
      var pc = document.getElementById('shipping_postal_code').value.replace(/[^0-9]/g, '');
      if (pc.length !== 7) { alert('郵便番号は7桁で入力してください'); return; }
      zipBtn.disabled = true; zipBtn.textContent = '検索中...';
      fetch('https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + pc)
        .then(function(r){ if (!r.ok) throw new Error('HTTP'); return r.json(); })
        .then(function(d){
          if (!d.results || !d.results.length) { alert('該当住所が見つかりません'); return; }
          var r = d.results[0];
          document.getElementById('shipping_prefecture').value = r.address1 || '';
          document.getElementById('shipping_city').value = r.address2 || '';
          document.getElementById('shipping_address_line').value = r.address3 || '';
        })
        .catch(function(){ alert('APIに接続できませんでした'); })
        .finally(function(){ zipBtn.disabled = false; zipBtn.textContent = '住所検索'; });
    });
  }
  var sel = document.getElementById('address_id');
  if (sel) {
    var fields = ['shipping_name','shipping_postal_code','shipping_prefecture','shipping_city','shipping_address_line','shipping_building','shipping_phone'];
    var map = {'shipping_name':'name','shipping_postal_code':'postal','shipping_prefecture':'pref','shipping_city':'city','shipping_address_line':'line','shipping_building':'building','shipping_phone':'phone'};
    function setReadonly(ro){ fields.forEach(function(id){ var el = document.getElementById(id); if (el) el.readOnly = ro; }); }
    function fill(opt){
      if (!opt || !opt.value) { fields.forEach(function(id){ var el = document.getElementById(id); if (el) el.value = ''; }); setReadonly(false); return; }
      Object.keys(map).forEach(function(id){ var el = document.getElementById(id); if (el) el.value = opt.getAttribute('data-' + map[id]) || ''; });
      setReadonly(true);
    }
    sel.addEventListener('change', function(){ fill(sel.options[sel.selectedIndex]); });
    fill(sel.options[sel.selectedIndex]);
  }
});
</script>
<?php require_once '../../meetory-footer.php'; ?>
