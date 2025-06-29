<tr>
<td class="header">
<a href="<?php echo new \Illuminate\Support\EncodedHtmlString($url); ?>" style="display: inline-block;">
<?php if(trim($slot) === config('app.name')): ?>
<img src="https://laravel.com/img/notification-logo.png" class="logo" alt="Laravel Logo">
<?php else: ?>
<?php echo new \Illuminate\Support\EncodedHtmlString($slot); ?>

<?php endif; ?>
</a>
</td>
</tr> <?php /**PATH C:\Users\ROYAL COMPUTER\Desktop\projets\laravel\rencontre\resources\views/vendor/mail/html/header.blade.php ENDPATH**/ ?>