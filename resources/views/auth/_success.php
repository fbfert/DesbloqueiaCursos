<?php if (!empty($success)): ?>
    <div class="auth-message auth-message-success">
        <p>
            <?php if (is_array($success) && !empty($success['message'])): ?>
                <?php echo htmlspecialchars((string) $success['message'], ENT_QUOTES, 'UTF-8'); ?> 
                <?php if (!empty($success['link']['href']) && !empty($success['link']['label'])): ?>
                    <a href="<?php echo htmlspecialchars((string) $success['link']['href'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string) $success['link']['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <?php echo htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
        </p>
    </div>
<?php endif; ?>
