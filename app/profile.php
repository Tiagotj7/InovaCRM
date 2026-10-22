<?php
declare(strict_types=1);

/* ---------- Avatar (foto de perfil) ---------- */

const AVATAR_DIR_NAME = 'uploads/avatars';
const AVATAR_MAX_BYTES = 4 * 1024 * 1024;

function avatar_dir(): string { return dirname(__DIR__) . '/public/' . AVATAR_DIR_NAME; }

/** Iniciais para o avatar sem foto (ex.: "Maria Souza" -> "MS"). */
function user_initials(string $name): string {
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$parts) return '?';
    $first = mb_strtoupper(mb_substr($parts[0], 0, 1));
    $last = count($parts) > 1 ? mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1)) : '';
    return $first . $last;
}

/** HTML do avatar: foto se houver, senão um círculo com as iniciais. */
function avatar_html(array $u, string $class = ''): string {
    $file = (string)($u['avatar'] ?? '');
    $class = trim('avatar ' . $class);
    if ($file !== '' && preg_match('/^[A-Za-z0-9_.-]+$/', $file)) {
        return '<img class="' . e($class) . '" src="' . AVATAR_DIR_NAME . '/' . e($file) . '" alt="" loading="lazy">';
    }
    return '<span class="' . e($class) . ' avatar-initials" aria-hidden="true">' . e(user_initials((string)($u['name'] ?? ''))) . '</span>';
}

function role_text(string $r): string {
    return ['global_admin' => 'Administrador geral', 'owner' => 'Responsável', 'member' => 'Membro'][$r] ?? $r;
}

/**
 * Valida e transforma a imagem enviada: confere o tipo real do arquivo, corta em
 * quadrado, reduz e regrava como JPEG (isso remove qualquer conteúdo escondido).
 * Retorna [nome_do_arquivo, null] em sucesso ou [null, mensagem_de_erro].
 */
function process_avatar_upload(array $file, int $userId): array {
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) return [null, 'Escolha uma imagem.'];
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return [null, 'A imagem é grande demais (máx. 4 MB).'];
    if ($err !== UPLOAD_ERR_OK) return [null, 'Não foi possível enviar a imagem. Tente novamente.'];
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) return [null, 'Envio inválido.'];
    if ((int)($file['size'] ?? 0) > AVATAR_MAX_BYTES) return [null, 'A imagem é grande demais (máx. 4 MB).'];

    $mime = '';
    if (function_exists('finfo_open')) { $fi = finfo_open(FILEINFO_MIME_TYPE); $mime = (string)finfo_file($fi, $tmp); finfo_close($fi); }
    $info = @getimagesize($tmp);
    if ($mime === '' && $info) $mime = (string)$info['mime'];
    if (!$info || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return [null, 'Use uma imagem JPG, PNG ou WebP.'];
    if (!function_exists('imagecreatefromstring')) return [null, 'O servidor não tem suporte para processar imagens (extensão GD).'];

    $src = @imagecreatefromstring((string)file_get_contents($tmp));
    if (!$src) return [null, 'Não foi possível ler a imagem.'];

    // Corrige a rotação de fotos de celular (EXIF).
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $rot = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
        if ($rot !== 0) { $r = imagerotate($src, $rot, 0); if ($r) { imagedestroy($src); $src = $r; } }
    }

    $w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
    $size = 320;
    $dst = imagecreatetruecolor($size, $size);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // fundo branco p/ PNG transparente
    imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);
    imagedestroy($src);

    $dir = avatar_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { imagedestroy($dst); return [null, 'Não foi possível salvar a imagem no servidor.']; }
    $name = 'u' . $userId . '_' . bin2hex(random_bytes(8)) . '.jpg';
    $ok = @imagejpeg($dst, $dir . '/' . $name, 86);
    imagedestroy($dst);
    if (!$ok) return [null, 'Não foi possível salvar a imagem no servidor.'];
    return [$name, null];
}

function delete_avatar_file(?string $name): void {
    if ($name && preg_match('/^[A-Za-z0-9_.-]+$/', $name)) @unlink(avatar_dir() . '/' . $name);
}

/* ---------- Página "Meu perfil" ---------- */

function profile_page(): void {
    $me = current_user();
    $id = (int)$me['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = post('action');
        $row = db()->prepare('SELECT * FROM users WHERE id=?'); $row->execute([$id]); $cur = $row->fetch();
        if (!$cur) { flash('error', 'Usuário não encontrado.'); redirect('index.php'); }

        if ($action === 'update_profile') {
            $name = post('name'); $email = strtolower(post('email')); $job = post('job_title'); $phone = post('phone');
            $err = '';
            if ($name === '' || mb_strlen($name) > 160) $err = 'Informe seu nome (até 160 caracteres).';
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $err = 'E-mail inválido.';
            elseif (mb_strlen($job) > 80) $err = 'O cargo pode ter até 80 caracteres.';
            elseif (mb_strlen($phone) > 40 || ($phone !== '' && !preg_match('/^[0-9+()\-\s.]{6,40}$/', $phone))) $err = 'Telefone inválido.';
            else {
                $ex = db()->prepare('SELECT 1 FROM users WHERE email=? AND id<>?'); $ex->execute([$email, $id]);
                if ($ex->fetch()) $err = 'Já existe outro usuário com este e-mail.';
            }
            if ($err !== '') { flash('error', $err); redirect('index.php?page=profile'); }
            db()->prepare('UPDATE users SET name=?,email=?,job_title=?,phone=? WHERE id=?')->execute([$name, $email, $job ?: null, $phone ?: null, $id]);
            flash('success', 'Perfil atualizado.');
            redirect('index.php?page=profile');
        }

        if ($action === 'upload_avatar') {
            [$fileName, $err] = process_avatar_upload($_FILES['avatar'] ?? [], $id);
            if ($err !== null) { flash('error', $err); redirect('index.php?page=profile'); }
            db()->prepare('UPDATE users SET avatar=? WHERE id=?')->execute([$fileName, $id]);
            delete_avatar_file($cur['avatar'] ?? null);
            flash('success', 'Foto de perfil atualizada.');
            redirect('index.php?page=profile');
        }

        if ($action === 'remove_avatar') {
            db()->prepare('UPDATE users SET avatar=NULL WHERE id=?')->execute([$id]);
            delete_avatar_file($cur['avatar'] ?? null);
            flash('success', 'Foto removida.');
            redirect('index.php?page=profile');
        }

        if ($action === 'change_password') {
            $old = (string)($_POST['current_password'] ?? ''); $new = (string)($_POST['new_password'] ?? ''); $conf = (string)($_POST['confirm_password'] ?? '');
            $err = '';
            if (!password_verify($old, (string)$cur['password_hash'])) $err = 'A senha atual está incorreta.';
            elseif (strlen($new) < 8) $err = 'A nova senha deve ter pelo menos 8 caracteres.';
            elseif ($new !== $conf) $err = 'A confirmação não confere com a nova senha.';
            elseif (hash_equals($old, $new)) $err = 'A nova senha deve ser diferente da atual.';
            if ($err !== '') { flash('error', $err); redirect('index.php?page=profile'); }
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $id]);
            session_regenerate_id(true);
            audit('password_changed', 'user', $id, 'Senha alterada pelo próprio usuário');
            flash('success', 'Senha alterada com sucesso.');
            redirect('index.php?page=profile');
        }

        redirect('index.php?page=profile');
    }

    $q = db()->prepare('SELECT u.*, c.name company_name FROM users u JOIN companies c ON c.id=u.company_id WHERE u.id=?');
    $q->execute([$id]); $u = $q->fetch();
    if (!$u) redirect('index.php');
    $plan = strtoupper((string)($me['plan_code'] ?? 'free'));
    if (is_global_admin()) $plan = 'ADMINISTRADOR GERAL';
    $fmt = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';

    layout_start('Meu perfil'); ?>
<section class="card profile-hero">
  <div class="profile-cover"></div>
  <div class="profile-head">
    <div class="avatar-wrap">
      <?= avatar_html($u, 'avatar-xl') ?>
      <form method="post" enctype="multipart/form-data" class="avatar-form">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="upload_avatar">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= AVATAR_MAX_BYTES ?>">
        <input type="file" name="avatar" id="avatar-input" accept="image/jpeg,image/png,image/webp" hidden onchange="this.form.submit()">
        <label for="avatar-input" class="avatar-edit" title="Alterar foto" aria-label="Alterar foto">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </label>
      </form>
    </div>
    <div class="profile-id">
      <h2><?= e($u['name']) ?></h2>
      <p><?= e($u['job_title'] ?: 'Adicione seu cargo') ?> · <?= e($u['company_name']) ?></p>
      <div class="profile-tags"><span class="badge pro"><?= e(role_text((string)$u['role'])) ?></span><?php if (!is_global_admin()): ?><span class="badge"><?= e('Plano ' . $plan) ?></span><?php endif; ?></div>
    </div>
    <?php if (!empty($u['avatar'])): ?>
    <form method="post" class="profile-remove" onsubmit="return confirm('Remover sua foto de perfil?');">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="remove_avatar">
      <button class="btn small danger">Remover foto</button>
    </form>
    <?php endif; ?>
  </div>
  <p class="profile-hint">Formatos JPG, PNG ou WebP, até 4 MB. A imagem é cortada em quadrado automaticamente.</p>
  <dl class="profile-meta">
    <div><dt>E-mail</dt><dd><?= e($u['email']) ?></dd></div>
    <div><dt>Telefone</dt><dd><?= e($u['phone'] ?: '—') ?></dd></div>
    <div><dt>Membro desde</dt><dd><?= e($fmt($u['created_at'])) ?></dd></div>
    <div><dt>Último acesso</dt><dd><?= e($u['last_login_at'] ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : '—') ?></dd></div>
  </dl>
</section>

<div class="grid-2 profile-grid">
  <section class="card">
    <div class="card-head"><h3>Informações pessoais</h3></div>
    <form method="post" autocomplete="on">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="update_profile">
      <div class="form-grid">
        <label>Nome completo<input name="name" required maxlength="160" value="<?= e($u['name']) ?>" autocomplete="name"></label>
        <label>Cargo<input name="job_title" maxlength="80" placeholder="Ex.: Gerente comercial" value="<?= e($u['job_title'] ?? '') ?>" autocomplete="organization-title"></label>
        <label>E-mail de acesso<input type="email" name="email" required maxlength="190" value="<?= e($u['email']) ?>" autocomplete="email"></label>
        <label>Telefone<input type="tel" name="phone" maxlength="40" placeholder="(00) 00000-0000" value="<?= e($u['phone'] ?? '') ?>" autocomplete="tel"></label>
      </div>
      <button class="btn primary">Salvar alterações</button>
    </form>
  </section>

  <section class="card">
    <div class="card-head"><h3>Segurança</h3></div>
    <form method="post" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="change_password">
      <div class="form-grid one">
        <label>Senha atual<input type="password" name="current_password" required autocomplete="current-password"></label>
        <label>Nova senha (mín. 8)<input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
        <label>Confirmar nova senha<input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label>
      </div>
      <button class="btn primary">Alterar senha</button>
    </form>
  </section>
</div>
<?php layout_end(); }
