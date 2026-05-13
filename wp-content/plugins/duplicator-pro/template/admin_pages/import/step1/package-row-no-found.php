<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

?>
<tr class="dupli-import-no-package-found">
    <td colspan="4" >
        <div class="dupli-import-no-package-found-msg">
           <b><?php esc_html_e("No archive files found!", 'duplicator-pro'); ?></b><br/><br/>
           <?php esc_html_e("Please upload a Duplicator archive.zip/daf in the area above.", 'duplicator-pro'); ?><br/>
           <?php esc_html_e("This will start the import process to overwrite the current site.", 'duplicator-pro'); ?>
           <br/><br/>
               <a href="<?php echo esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'import-install'); ?>" target="_blank">
               <?php esc_html_e('How does this work?', 'duplicator-pro'); ?>
           </a>
        </div>
    </td>
</tr>
