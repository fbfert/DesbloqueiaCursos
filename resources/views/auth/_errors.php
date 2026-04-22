<?php if (!empty($errors)): ?>
    <div class="auth-message auth-message-error">
        <?php foreach ($errors as $error): ?>
            <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
