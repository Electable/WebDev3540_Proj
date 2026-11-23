<?php
require_once __DIR__ . '/config.php';
if (current_user()) redirect('eventhub_landing.php');

$errors = ['form' => '', 'email' => '', 'username' => '', 'password' => ''];
$old    = ['email' => '', 'username' => '', 'role' => 'customer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['email']    = trim((string)($_POST['email'] ?? ''));
    $old['username'] = trim((string)($_POST['username'] ?? ''));
    $old['role']     = ($_POST['role'] ?? '') === 'organiser' ? 'organiser' : 'customer';
    $password        = (string)($_POST['password'] ?? '');

    if (!csrf_ok()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        if (!valid_email($old['email']))           $errors['email']    = 'Enter a valid email address.';
        if ($m = valid_username($old['username'])) $errors['username'] = $m;
        if ($m = valid_password($password))        $errors['password'] = $m;

        // Does an account already exist?
        if (!$errors['email'] || !$errors['username']) {
            $st = db()->prepare('SELECT Email, Username FROM Users WHERE Email = ? OR Username = ?');
            $st->execute([$old['email'], $old['username']]);
            foreach ($st->fetchAll() as $row) {
                if (strcasecmp($row['Email'], $old['email']) === 0)
                    $errors['email'] = 'An account with this email already exists. Try signing in instead.';
                if (strcasecmp($row['Username'], $old['username']) === 0)
                    $errors['username'] = 'That username is taken. Pick another.';
            }
        }

        if (!array_filter($errors)) {
            $pdo  = db();
            $role = $old['role'] === 'organiser' ? 'Organizer' : 'Customer';
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO Users (Username, Email, PasswordHash, Role, RecordDateTime) VALUES (?, ?, ?, ?, NOW())')
                    ->execute([$old['username'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $role]);
                $uid = (int)$pdo->lastInsertId();
                $pdo->prepare('UPDATE Users SET CreatedBy = ? WHERE UserID = ?')->execute([$uid, $uid]);

                if ($role === 'Customer') {
                    $pdo->prepare('INSERT INTO Customers (UserID, RecordDateTime, CreatedBy) VALUES (?, NOW(), ?)')->execute([$uid, $uid]);
                } else {
                    $pdo->prepare('INSERT INTO EventOrganizers (UserID, RecordDateTime, CreatedBy) VALUES (?, NOW(), ?)')->execute([$uid, $uid]);
                }
                $pdo->commit();
                log_in(['UserID' => $uid]);
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($ex->getCode() === '23000') {   // lost a race with another signup
                    $errors['form'] = 'An account with that email or username already exists.';
                } else {
                    throw $ex;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>EventHub — Sign Up</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Gloock&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }

    :root {
      --ink: #050505;
      --line: #b9b9b9;
      --muted: #e8e8e8;
      --selected: #b7b7b7;
    }

    body {
      margin: 0;
      min-height: 100vh;
      background: #fff;
      color: var(--ink);
      font-family: "Gloock", Georgia, serif;
    }

    .page {
      width: min(100% - 48px, 802px);
      min-height: 100vh;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 273px 424px;
      column-gap: 104px;
      align-items: start;
      padding-top: 67px;
    }

    .left-panel {
      width: 100%;
      padding-top: 21px;
    }

    .brand {
      text-align: center;
      font-size: 14px;
      line-height: 18px;
      margin-bottom: 52px;
    }

    h1 {
      font-size: 24px;
      line-height: 1.18;
      letter-spacing: -1.15px;
      font-weight: 400;
      margin: 0 0 6px;
      white-space: nowrap;
    }

    .subtitle {
      font-size: 11px;
      line-height: 1.3;
      margin: 0 0 19px;
      white-space: nowrap;
    }

    .field {
      margin-bottom: 13px;
    }

    .field label {
      display: block;
      font-size: 10px;
      line-height: 14px;
      margin-bottom: 2px;
    }

    .field input {
      width: 100%;
      height: 29px;
      border: 1px solid var(--line);
      border-radius: 7px;
      background: #fff;
      padding: 4px 9px;
      font: 12px "Gloock", Georgia, serif;
      outline: none;
    }

    .field input:focus {
      border-color: #555;
      box-shadow: 0 0 0 2px rgba(0,0,0,.06);
    }

    .role-picker {
      border: 1px solid var(--line);
      border-radius: 8px;
      height: 57px;
      padding: 5px 10px 7px;
      margin-top: 20px;
      margin-bottom: 19px;
    }

    .role-title {
      display: block;
      font-size: 10px;
      line-height: 15px;
      margin-bottom: 7px;
    }

    .role-options {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 11px;
    }

    .role-options input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .role-options label {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 19px;
      border-radius: 8px;
      background: var(--muted);
      font-size: 9px;
      cursor: pointer;
      user-select: none;
      transition: background .15s ease;
    }

    .role-options input:checked + label {
      background: var(--selected);
    }

    .role-options input:focus-visible + label {
      outline: 2px solid #555;
      outline-offset: 2px;
    }

    .action {
      width: 100%;
      height: 35px;
      border-radius: 20px;
      font: 12px "Gloock", Georgia, serif;
      cursor: pointer;
    }

    .primary {
      color: #fff;
      background: #000;
      border: 1px solid #000;
      margin-bottom: 13px;
    }

    .google {
      color: #000;
      background: #fff;
      border: 1px solid var(--line);
    }

    .account-link {
      display: block;
      width: fit-content;
      margin: 13px auto 0;
      color: #000;
      font-size: 8px;
      text-decoration: underline;
      text-underline-offset: 1px;
    }

    .visual-panel {
      width: 424px;
      height: 522px;
      border-radius: 11px;
      overflow: hidden;
      background:
        repeating-linear-gradient(
          90deg,
          rgba(112, 68, 53, .10) 0px,
          rgba(112, 68, 53, .10) 4px,
          rgba(255, 221, 198, .25) 8px,
          rgba(255, 239, 221, .30) 13px,
          rgba(177, 113, 89, .17) 18px,
          rgba(255, 224, 203, .25) 23px,
          rgba(139, 84, 67, .10) 27px
        ),
        linear-gradient(105deg, #d99e83, #f2c2a6 48%, #d99d83);
      box-shadow: inset 0 0 30px rgba(119, 72, 54, .13);
      position: relative;
    }

    .visual-panel::after {
      content: "";
      position: absolute;
      inset: 0;
      background: repeating-linear-gradient(
        90deg,
        transparent 0 9px,
        rgba(255,255,255,.14) 10px 13px,
        transparent 17px 26px
      );
      filter: blur(3px);
      opacity: .8;
    }

    @media (max-width: 900px) {
      .page {
        width: min(100% - 40px, 520px);
        grid-template-columns: 1fr;
        gap: 36px;
        padding: 36px 0;
      }
      .left-panel {
        max-width: 360px;
        margin: 0 auto;
        padding-top: 0;
      }
      .brand { margin-bottom: 36px; }
      h1 { white-space: normal; }
      .subtitle { white-space: normal; }
      .visual-panel {
        width: 100%;
        height: min(65vw, 420px);
        grid-row: 1;
      }
    }

    @media (max-width: 420px) {
      .page { width: min(100% - 36px, 360px); }
      .visual-panel { height: 260px; }
    }
  .field .fe{margin:3px 0 0;font-size:9px;line-height:12px;color:#b3261e}
.fe:empty{display:none}
.form-error{margin:0 0 12px;font-size:10px;color:#b3261e}
.field input[aria-invalid="true"]{border-color:#b3261e}
.role-picker{min-width:0}
</style>
</head>
<body>
  <main class="page">
    <section class="left-panel" aria-label="Create an EventHub account">
      <a class="brand" href="eventhub_landing.php" style="display:block;color:inherit;text-decoration:none">EventHub</a>
      <h1>Welcome to EventHub</h1>
      <p class="subtitle">Tickets, Orders, and Events in One Place</p>

      <form id="signup-form" method="post" action="eventhub_signup.php" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <?php if ($errors['form']): ?><p class="form-error" role="alert"><?= e($errors['form']) ?></p><?php endif; ?>

        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" autocomplete="email" value="<?= e($old['email']) ?>"<?= $errors['email'] ? ' aria-invalid="true"' : '' ?> />
          <p class="fe" role="alert"><?= e($errors['email']) ?></p>
        </div>

        <div class="field">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" autocomplete="username" maxlength="50" value="<?= e($old['username']) ?>"<?= $errors['username'] ? ' aria-invalid="true"' : '' ?> />
          <p class="fe" role="alert"><?= e($errors['username']) ?></p>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" autocomplete="new-password"<?= $errors['password'] ? ' aria-invalid="true"' : '' ?> />
          <p class="fe" role="alert"><?= e($errors['password']) ?></p>
        </div>

        <fieldset class="role-picker">
          <legend class="role-title">I am signing up as ....</legend>
          <div class="role-options">
            <input type="radio" id="customer" name="role" value="customer"<?= $old['role'] === 'customer' ? ' checked' : '' ?> />
            <label for="customer">Customer</label>
            <input type="radio" id="organiser" name="role" value="organiser"<?= $old['role'] === 'organiser' ? ' checked' : '' ?> />
            <label for="organiser">Event Organiser</label>
          </div>
        </fieldset>

        <button class="action primary" type="submit">Sign Up</button>
        <button class="action google" type="button" id="google-signin" title="Google sign-in isn't connected yet">Continue with Google</button>
        <a class="account-link" href="eventhub_signIn.php" id="MovetoLoginBtn">Already have an Account?</a>
      </form>
    </section>

    <div class="visual-panel" role="img" aria-label="Warm peach vertical striped abstract texture"></div>
  </main>
</body>
</html>
