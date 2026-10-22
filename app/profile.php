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
    if (!function_exists('imagecreatefromstring')) { app_log('avatar: extensão GD ausente'); return [null, 'O servidor não tem suporte para processar imagens (extensão GD).']; }
    // Foto de celular tem muitos megapixels e consome muita memória ao decodificar.
    if ((int)$info[0] * (int)$info[1] > 30000000) return [null, 'A imagem tem resolução muito alta. Reduza o tamanho e tente de novo.'];
    @ini_set('memory_limit', '256M');

    $src = @imagecreatefromstring((string)file_get_contents($tmp));
    if (!$src) { app_log('avatar: imagecreatefromstring falhou (mime ' . $mime . ')'); return [null, 'Não foi possível ler a imagem. Tente outro arquivo JPG ou PNG.']; }

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
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { imagedestroy($dst); app_log('avatar: não foi possível criar ' . $dir); return [null, 'Não foi possível salvar a imagem no servidor (pasta de fotos sem permissão).']; }
    if (!is_writable($dir)) { @chmod($dir, 0755); }
    if (!is_writable($dir)) { imagedestroy($dst); app_log('avatar: pasta sem permissão de escrita ' . $dir); return [null, 'A pasta de fotos do servidor não permite gravação. Ajuste a permissão de public/uploads/avatars para 755 ou 775.']; }
    $name = 'u' . $userId . '_' . bin2hex(random_bytes(8)) . '.jpg';
    $ok = @imagejpeg($dst, $dir . '/' . $name, 86);
    imagedestroy($dst);
    if (!$ok) { app_log('avatar: imagejpeg falhou em ' . $dir); return [null, 'Não foi possível salvar a imagem no servidor.']; }
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
        // Arquivo maior que post_max_size: o PHP descarta o formulário inteiro (inclusive o token).
        if (!$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            flash('error', 'A imagem é grande demais para o servidor. Escolha uma foto menor.');
            redirect('index.php?page=profile');
        }
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
            try { [$fileName, $err] = process_avatar_upload($_FILES['avatar'] ?? [], $id); }
            catch (Throwable $t) { app_log('avatar: ' . get_class($t) . ': ' . $t->getMessage()); [$fileName, $err] = [null, 'Falha ao processar a imagem. Tente uma foto menor.']; }
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
        <input type="file" name="avatar" id="avatar-input" accept="image/jpeg,image/png,image/webp" hidden>
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
<script>
(function(){
  var input=document.getElementById('avatar-input'); if(!input) return;
  var form=input.form, SIZE=480;
  function fail(msg){ var d=document.createElement('div'); d.className='flash error'; d.textContent=msg; var m=document.querySelector('.main'); m.insertBefore(d,m.querySelector('.topbar').nextSibling); window.scrollTo(0,0); }
  function load(file){
    if(window.createImageBitmap) return createImageBitmap(file,{imageOrientation:'from-image'}).catch(function(){return createImageBitmap(file);});
    return new Promise(function(ok,no){var i=new Image(),u=URL.createObjectURL(file);i.onload=function(){ok(i);};i.onerror=no;i.src=u;});
  }
  input.addEventListener('change',function(){
    var f=input.files&&input.files[0]; if(!f) return;
    if(!/^image\/(jpeg|png|webp)$/.test(f.type)){ fail('Use uma imagem JPG, PNG ou WebP.'); input.value=''; return; }
    document.body.classList.add('avatar-busy');
    load(f).then(function(img){
      var w=img.width,h=img.height,s=Math.min(w,h),c=document.createElement('canvas'); c.width=c.height=SIZE;
      var x=c.getContext('2d'); x.fillStyle='#fff'; x.fillRect(0,0,SIZE,SIZE); x.imageSmoothingQuality='high';
      x.drawImage(img,(w-s)/2,(h-s)/2,s,s,0,0,SIZE,SIZE);
      c.toBlob(function(b){
        if(!b){ form.submit(); return; }
        var fd=new FormData(form); fd.set('avatar',b,'avatar.jpg'); fd.delete('MAX_FILE_SIZE');
        fetch(form.getAttribute('action')||location.href,{method:'POST',body:fd,credentials:'same-origin',redirect:'manual'})
          .then(function(){ location.href='index.php?page=profile'; })
          .catch(function(){ location.href='index.php?page=profile'; });
      },'image/jpeg',0.88);
    }).catch(function(){ form.submit(); });
  });
})();
</script>
<?php layout_end(); }
