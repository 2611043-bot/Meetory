<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once '../../meetory-functions.php';
$pdo = getPDO();
if (!isset($_SESSION['user_id']) || filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false || (int)$_SESSION['user_id'] <= 0) { header('Location: login.php'); exit; }
if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$prefectures = ['北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県','茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県','新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県','静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県','徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県'];
require '../../meetory-header.php';
?>
<h1>住所登録</h1>
<form method="post" action="address-register-process.php">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
<div class="form-group"><label>氏名</label><br><input type="text" name="recipient_name" required maxlength="100"></div>
<div class="form-group"><label>郵便番号</label><br><input type="text" name="postal_code" id="postal_code" required maxlength="8"> <button type="button" id="zipSearchBtn">住所検索</button> <button type="button" id="zipRevBtn">郵便番号検索</button></div>
<div class="form-group"><label>都道府県</label><br><select name="prefecture" required><?php foreach($prefectures as $p): ?><option value="<?= htmlspecialchars($p,ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($p,ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>市区町村</label><br><input type="text" name="city" required maxlength="100"></div>
<div class="form-group"><label>番地</label><br><input type="text" name="address_line" required maxlength="255"></div>
<div class="form-group"><label>建物名</label><br><input type="text" name="building" maxlength="255"></div>
<div class="form-group"><label>電話番号</label><br><input type="text" name="phone" required maxlength="20"></div>
<div class="form-group"><label><input type="checkbox" name="is_default" value="1"> デフォルト住所にする</label></div>
<button type="submit">登録</button>
</form>
<script>
(function(){
  var btn=document.getElementById('zipRevBtn');
  if(!btn) return;
  btn.addEventListener('click',function(){
    var pref=document.querySelector('select[name="prefecture"]').value;
    var city=document.querySelector('input[name="city"]').value;
    var line=document.querySelector('input[name="address_line"]').value;
    var q=encodeURIComponent((pref+' '+city+' '+line).trim());
    if(!q){ alert('住所を入力してください'); return; }
    btn.disabled=true; btn.textContent='検索中...';
    fetch('https://zipcoda.net/api?address='+q)
      .then(function(r){ if(!r.ok) throw new Error('HTTP'); return r.json(); })
      .then(function(d){
        if(!d.items || !d.items.length){ alert('該当郵便番号が見つかりません'); return; }
        if(d.items.length===1){ document.getElementById('postal_code').value=d.items[0].zipcode; return; }
        var sel=document.createElement('select');
        d.items.slice(0,20).forEach(function(it){ var o=document.createElement('option'); o.value=it.zipcode; o.textContent=it.zipcode+' '+it.address; sel.appendChild(o); });
        sel.onchange=function(){ document.getElementById('postal_code').value=sel.value; };
        btn.parentNode.appendChild(sel);
      })
      .catch(function(){ alert('APIに接続できませんでした'); })
      .finally(function(){ btn.disabled=false; btn.textContent='郵便番号検索'; });
  });
})();
</script>
  if(!btn) return;
  btn.addEventListener('click',function(){
    var pc=document.getElementById('postal_code').value.replace(/[^0-9]/g,'');
    if(pc.length!==7){ alert('郵便番号は7桁で入力してください'); return; }
    btn.disabled=true; btn.textContent='検索中...';
    fetch('https://zipcloud.ibsnet.co.jp/api/search?zipcode='+pc)
      .then(function(r){ if(!r.ok) throw new Error('HTTP'); return r.json(); })
      .then(function(d){
        if(!d.results || !d.results.length){ alert('該当住所が見つかりません'); return; }
        var r=d.results[0];
        var pref=document.querySelector('select[name="prefecture"]');
        if(pref) pref.value=r.address1;
        document.querySelector('input[name="city"]').value=r.address2||'';
        document.querySelector('input[name="address_line"]').value=r.address3||'';
      })
      .catch(function(){ alert('APIに接続できませんでした'); })
      .finally(function(){ btn.disabled=false; btn.textContent='住所検索'; });
  });
})();
</script>
<?php require '../../meetory-footer.php'; ?>
