<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../../meetory-functions.php';

if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    $query  = $_SERVER['QUERY_STRING'] ?? '';
    $return = '/Meetory/meetory/reports/report.php' . ($query !== '' ? '?' . $query : '');
    header('Location: ../user/login.php?return_url=' . urlencode($return));
    exit;
}

$login_user_id = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = getPDO();

$is_post   = ($_SERVER['REQUEST_METHOD'] === 'POST');
$completed = (isset($_GET['completed']) && $_GET['completed'] === '1');

$target_type = $_POST['target_type'] ?? $_GET['target_type'] ?? '';
$target_type = is_string($target_type) ? $target_type : '';

$target_id = filter_var(
    $_POST['target_id'] ?? $_GET['target_id'] ?? '',
    FILTER_VALIDATE_INT
);
if ($target_id === false || $target_id === null) {
    $target_id = 0;
}

$reason      = isset($_POST['reason']) && is_string($_POST['reason']) ? trim($_POST['reason']) : '';
$description = isset($_POST['description']) && is_string($_POST['description']) ? trim($_POST['description']) : '';
$confirm     = $_POST['confirm'] ?? '';
$back        = $_POST['back'] ?? '';

if ($is_post) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (
        !is_string($csrf_token) ||
        $csrf_token === '' ||
        !hash_equals($_SESSION['csrf_token'], $csrf_token)
    ) {
        exit('不正なリクエストです。ページを開き直してください。');
    }
}

function load_report_target(PDO $pdo, string $target_type, int $target_id): ?array
{
    if ($target_id <= 0) {
        return null;
    }

    if ($target_type === 'goods') {
        $stmt = $pdo->prepare(
            'SELECT goods_id, seller_id, goods_name FROM goods WHERE goods_id = ? LIMIT 1'
        );
        $stmt->execute([$target_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return [
            'name'      => $row['goods_name'],
            'seller_id' => (int) $row['seller_id'],
        ];
    }

    if ($target_type === 'user') {
        $stmt = $pdo->prepare(
            'SELECT user_id, user_name FROM users WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$target_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return [
            'name'      => $row['user_name'],
            'seller_id' => null,
        ];
    }

    return null;
}

function validate_report_input(
    PDO $pdo,
    int $login_user_id,
    string $target_type,
    int $target_id,
    string $reason,
    string $description,
    bool $check_duplicate
): array {
    $errors = [];

    if (!isValidReportTargetType($target_type)) {
        return ['errors' => ['通報対象の種類が正しくありません。'], 'target' => null];
    }

    $target = load_report_target($pdo, $target_type, $target_id);
    if ($target === null) {
        return ['errors' => ['通報対象が見つかりません。'], 'target' => null];
    }

    if ($target_type === 'user' && $target_id === $login_user_id) {
        $errors[] = '自分自身を通報することはできません。';
    } elseif ($target_type === 'goods' && $target['seller_id'] === $login_user_id) {
        $errors[] = '自分が出品した商品を通報することはできません。';
    }

    if (!in_array($reason, getReportReasons($target_type), true)) {
        $errors[] = '通報理由が正しくありません。';
    }

    if (mb_strlen($description, 'UTF-8') > 1000) {
        $errors[] = '詳細説明は1000文字以内で入力してください。';
    }

    if ($check_duplicate) {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM reports
             WHERE reporter_id = ? AND target_type = ? AND target_id = ?
             LIMIT 1'
        );
        $stmt->execute([$login_user_id, $target_type, $target_id]);
        if ((bool) $stmt->fetchColumn()) {
            $errors[] = '同じ対象をすでに通報済みです。';
        }
    }

    return ['errors' => $errors, 'target' => $target];
}

$errors = [];
$view   = 'input';
$target = null;

if ($completed && !$is_post) {
    $view = 'complete';
} elseif ($is_post && (string) $back === '1') {
    $view   = 'input';
    $target = load_report_target($pdo, $target_type, $target_id);
    if ($target === null) {
        $errors[] = '通報対象が見つかりません。';
    }
} elseif ($is_post && $confirm === '1') {
    $result = validate_report_input(
        $pdo,
        $login_user_id,
        $target_type,
        $target_id,
        $reason,
        $description,
        true
    );
    $errors = $result['errors'];
    $target = $result['target'];

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO reports
                    (reporter_id, target_type, target_id, reason, description, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $login_user_id,
                $target_type,
                $target_id,
                $reason,
                $description !== '' ? $description : null,
                'pending',
            ]);
        } catch (Throwable $e) {
            error_log('report insert error: ' . $e->getMessage());
            $errors[] = '通報の保存に失敗しました。時間をおいて再度お試しください。';
        }
    }

    if (empty($errors)) {
        header('Location: report.php?completed=1');
        exit;
    }

    $view = $target !== null ? 'confirm' : 'input';
} elseif ($is_post) {
    $result = validate_report_input(
        $pdo,
        $login_user_id,
        $target_type,
        $target_id,
        $reason,
        $description,
        true
    );
    $errors = $result['errors'];
    $target = $result['target'];
    $view   = empty($errors) ? 'confirm' : 'input';
} else {
    $view   = 'input';
    $target = load_report_target($pdo, $target_type, $target_id);

    if (!isValidReportTargetType($target_type)) {
        $errors[] = '通報対象の種類が正しくありません。';
    } elseif ($target === null) {
        $errors[] = '通報対象が見つかりません。';
    } elseif ($target_type === 'user' && $target_id === $login_user_id) {
        $errors[] = '自分自身を通報することはできません。';
    } elseif ($target_type === 'goods' && $target['seller_id'] === $login_user_id) {
        $errors[] = '自分が出品した商品を通報することはできません。';
    }
}

require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/reports.css">

<main class="report-page">

<?php if ($view === 'complete'): ?>

    <h1>通報が完了しました</h1>
    <p>通報を受け付けました。ご協力ありがとうございます。</p>
    <p>
        <a href="../index.php">ホームへ戻る</a>
    </p>

<?php else: ?>

    <?php if (!empty($errors)): ?>
        <div class="report-errors" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($view === 'input'): ?>

        <h1>通報</h1>

        <?php if ($target !== null): ?>
            <p class="report-target">
                通報対象：
                <?= htmlspecialchars(getReportTargetTypeLabel($target_type), ENT_QUOTES, 'UTF-8') ?>
                「<?= htmlspecialchars($target['name'], ENT_QUOTES, 'UTF-8') ?>」
            </p>
        <?php endif; ?>

        <form action="report.php" method="post" class="report-form">

            <input type="hidden" name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="target_type"
                value="<?= htmlspecialchars($target_type, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="target_id" value="<?= (int) $target_id ?>">

            <fieldset class="report-reasons">
                <legend>通報理由</legend>

                <?php foreach (getReportReasons($target_type) as $reason_option): ?>
                    <label class="report-reason">
                        <input
                            type="radio"
                            name="reason"
                            value="<?= htmlspecialchars($reason_option, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $reason === $reason_option ? 'checked' : '' ?>
                        >
                        <?= htmlspecialchars($reason_option, ENT_QUOTES, 'UTF-8') ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <p>
                <label for="report_description">詳細説明（任意・1000文字以内）</label><br>
                <textarea
                    id="report_description"
                    name="description"
                    rows="5"
                    cols="40"
                    maxlength="1000"
                ><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
            </p>

            <button type="submit">確認画面へ進む</button>

        </form>

    <?php else: ?>

        <h1>通報内容の確認</h1>

        <p class="report-target">
            通報対象：
            <?= htmlspecialchars(getReportTargetTypeLabel($target_type), ENT_QUOTES, 'UTF-8') ?>
            <?php if ($target !== null): ?>
                「<?= htmlspecialchars($target['name'], ENT_QUOTES, 'UTF-8') ?>」
            <?php endif; ?>
        </p>

        <p>通報理由：<?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?></p>

        <p>
            詳細説明：
            <?php if ($description !== ''): ?>
                <?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) ?>
            <?php else: ?>
                なし
            <?php endif; ?>
        </p>

        <form action="report.php" method="post" class="report-form">

            <input type="hidden" name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="target_type"
                value="<?= htmlspecialchars($target_type, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="target_id" value="<?= (int) $target_id ?>">
            <input type="hidden" name="reason"
                value="<?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="description"
                value="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="confirm" value="1">

            <button type="submit" name="back" value="1" class="report-back-btn">修正する</button>
            <button type="submit" class="report-submit-btn">この内容で通報する</button>

        </form>

    <?php endif; ?>

<?php endif; ?>

</main>

<?php require '../../meetory-footer.php'; ?>