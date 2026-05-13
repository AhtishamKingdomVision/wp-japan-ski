<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\ImportPageController;
use Duplicator\Views\UI\UiDialog;
use Duplicator\Views\UI\UiMessages;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

$packageDeteleConfirm                      = new UiDialog();
$packageDeteleConfirm->title               = __('Delete Backup?', 'duplicator-pro');
$packageDeteleConfirm->wrapperClassButtons = 'dupli-dlg-import-detete-package-btns';
$packageDeteleConfirm->progressOn          = false;
$packageDeteleConfirm->closeOnConfirm      = true;
$packageDeteleConfirm->message             = __('Are you sure you want to delete the selected Backup?', 'duplicator-pro');
$packageDeteleConfirm->jsCallback          = 'DupliJs.ImportManager.removePackage()';
$packageDeteleConfirm->initConfirm();

$packageInvalidName                  = new UiMessages(
    __(
        '<b>Invalid archive name:</b> The archive name must follow the Duplicator Backup name pattern
        e.g. BACKUP_NAME_[HASH]_[YYYYMMDDHHSS]_archive.zip (or with a .daf extension).
        <br>Please make sure not to rename the archive after downloading it and try again!',
        'duplicator-pro'
    ),
    UiMessages::ERROR
);
$packageInvalidName->hide_on_init    = true;
$packageInvalidName->is_dismissible  = true;
$packageInvalidName->auto_hide_delay = 10000;
$packageInvalidName->initMessage();

$packageAlreadyExists                  = new UiMessages(
    __('Archive file name already exists! <br>Please remove it and try again!', 'duplicator-pro'),
    UiMessages::ERROR
);
$packageAlreadyExists->hide_on_init    = true;
$packageAlreadyExists->is_dismissible  = true;
$packageAlreadyExists->auto_hide_delay = 5000;
$packageAlreadyExists->initMessage();

$packageUploaded                  = new UiMessages(__('Backup uploaded', 'duplicator-pro'), UiMessages::NOTICE);
$packageUploaded->hide_on_init    = true;
$packageUploaded->is_dismissible  = true;
$packageUploaded->auto_hide_delay = 5000;
$packageUploaded->initMessage();

$packageCancelUpload                  = new UiMessages(__('Backup upload cancelled', 'duplicator-pro'), UiMessages::ERROR);
$packageCancelUpload->hide_on_init    = true;
$packageCancelUpload->is_dismissible  = true;
$packageCancelUpload->auto_hide_delay = 5000;
$packageCancelUpload->initMessage();

$packageRemoved                  = new UiMessages(__('Backup removed', 'duplicator-pro'), UiMessages::NOTICE);
$packageRemoved->hide_on_init    = true;
$packageRemoved->is_dismissible  = true;
$packageRemoved->auto_hide_delay = 5000;
$packageRemoved->initMessage();

$importChunkSize = ImportPageController::getChunkSize();
?><script>
    jQuery(document).ready(function($) {
        var uploadFileMessageContent = <?php $tplMng->renderJson('admin_pages/import/import-message-upload-error'); ?>;

        DupliJs.ImportManager = {
            uploaderWrapper: $('#dupli-import-upload-tabs-wrapper'),
            uploaderDisabler: $('<div>'),
            uploader: $('#dupli-import-upload-file'),
            uploaderContent: $('#dupli-import-upload-file-content'),
            packageRowTemplate: $('#dupli-import-row-template'),
            packageRowNoFoundTemplate: $('#dupli-import-available-packages-templates .dupli-import-no-package-found'),
            packagesAviable: $('#dupli-import-available-packages'),
            packagesList: $('#dupli-import-available-packages .packages-list'),
            packageRowUploading: null,
            packageRowToDelete: null,
            autoLaunchAfterUpload: false,
            autoLaunchLink: false,
            confirmLaunchLink: false,
            startUpload: false,
            lastUploadsTimes: [],
            debug: true,
            init: function() {
                $('#dupli-import-instructions-toggle').click(function() {
                    $('#dupli-import-instructions-content').toggle(300);
                })

                DupliJs.ImportManager.uploaderWrapper.css('position', 'relative');
                DupliJs.ImportManager.uploaderDisabler = $('<div>').css({
                    'position': 'absolute',
                    'top': 0,
                    'left': 0,
                    'width': '100%',
                    'height': '100%',
                    'z-index': '10',
                    'cursor': 'not-allowed',
                    'display': 'none'
                });
                DupliJs.ImportManager.uploaderDisabler.appendTo(DupliJs.ImportManager.uploaderWrapper);

                DupliJs.ImportManager.uploader.upload({
                        autoUpload: true,
                        multiple: false,
                        maxSize: <?php echo empty($importChunkSize) ? (int) wp_max_upload_size() : 10737418240; ?>, //100GB get value from upload_max_filesize
                        maxConcurrent: 1,
                        maxFiles: 1,
                        postData: {
                            action: 'duplicator_import_upload',
                            nonce: <?php echo json_encode(wp_create_nonce('duplicator_import_upload')); ?>
                        },
                        chunkSize: <?php echo (int) $importChunkSize; ?>, // This is in kb
                        action: <?php echo json_encode(get_admin_url(null, 'admin-ajax.php')); ?>,
                        chunked: <?php echo empty($importChunkSize) ? 'false' : 'true'; ?>,
                        label: DupliJs.ImportManager.uploaderContent.parent().html(),
                        leave: '<?php echo esc_js(__('You have uploads pending, are you sure you want to leave this page?', 'duplicator-pro')); ?>'
                    })
                    .on("start.upload", DupliJs.ImportManager.onStart)
                    .on("complete.upload", DupliJs.ImportManager.onComplete)
                    .on("filestart.upload", DupliJs.ImportManager.onFileStart)
                    .on("fileprogress.upload", DupliJs.ImportManager.onFileProgress)
                    .on("filecomplete.upload", DupliJs.ImportManager.onFileComplete)
                    .on("fileerror.upload", DupliJs.ImportManager.onFileError)
                    .on("fileerror.chunkerror", DupliJs.ImportManager.onChunkError);

                DupliJs.ImportManager.uploaderContent.remove();
                DupliJs.ImportManager.uploaderContent = $('#dupli-import-upload-file #dupli-import-upload-file-content');
                DupliJs.ImportManager.initPageButtons();
                DupliJs.ImportManager.checkMaxUploadedFiles();

                DupliJs.ImportManager.packagesList.on('click', '.dupli-import-action-remove', function(event) {
                    event.stopPropagation();
                    DupliJs.ImportManager.packageRowToDelete = $(this).closest('.dupli-import-package');
                    <?php $packageDeteleConfirm->showConfirm(); ?>
                    return false;
                });

                DupliJs.ImportManager.packagesList.on('click', '.dupli-import-action-package-detail-toggle', function(event) {
                    event.stopPropagation();
                    let button = $(this);
                    let details = button.closest('.dupli-import-package').find('.dupli-import-package-detail');
                    if (details.hasClass('no-display')) {
                        button.find('.fa').removeClass('fa-caret-down').addClass('fa-caret-up');
                        details.removeClass('no-display');
                    } else {
                        button.find('.fa').removeClass('fa-caret-up').addClass('fa-caret-down');
                        details.addClass('no-display');
                    }
                    return false;
                });

                DupliJs.ImportManager.packagesList.on('click', '.dupli-import-action-cancel-upload', function(event) {
                    event.stopPropagation();
                    DupliJs.ImportManager.abortUpload();
                    <?php $packageCancelUpload->showMessage(); ?>
                    return false;
                });

                DupliJs.ImportManager.packagesList.on('click', '.dupli-import-action-install', function(event) {
                    event.stopPropagation();
                    DupliJs.ImportManager.confirmLaunchLink = $(this).data('install-url');
                    $('#dupli-import-phase-one').addClass('no-display');
                    $('#dupli-import-phase-two').removeClass('no-display');
                    return false;
                });

                DupliJs.ImportManager.packagesList.on('click', '.dup-import-set-archive-password', function(event) {
                    event.stopPropagation();
                    DupliJs.ImportManager.setArchivePassword($(this));
                    return false;
                });

                DupliJs.ImportManager.initRemoteUpload();
            },
            initRemoteUpload: function() {
                $('#dupli-import-remote-upload').click(function() {
                    let uploadUrl = $('#dupli-import-remote-url').val();
                    let parsedUrl = null;

                    try {
                        parsedUrl = new URL(uploadUrl);
                    } catch (error) {
                        DupliJs.addAdminMessage('<?php echo esc_js(__('Invalid URL', 'duplicator-pro')) ?>', 'error');
                    }

                    let files = [{
                        'name': parsedUrl.pathname.split('/').pop(),
                        'size': -1
                    }];
                    if (DupliJs.ImportManager.onStart(null, files) == false) {
                        DupliJs.ImportManager.onComplete(null);
                        return false;
                    }
                    DupliJs.ImportManager.onFileStart(null, files[0]);
                    DupliJs.ImportManager.remoteUploadCall(uploadUrl, null);
                });
            },
            remoteUploadCall: function(uploadUrl, restoreDownload) {
                DupliJs.Util.ajaxWrapper({
                        action: 'duplicator_import_remote_download',
                        url: uploadUrl,
                        restoreDownload: (restoreDownload == null ? '' : JSON.stringify(restoreDownload)),
                        nonce: '<?php echo esc_js(wp_create_nonce('duplicator_remote_download')); ?>'
                    },
                    function(result, data, funcData, textStatus, jqXHR) {
                        if (DupliJs.ImportManager.packageRowUploading == null) {
                            // if row don't exitst the upload is aboted
                            DupliJs.ImportManager.onComplete(null);
                            return '';
                        }

                        if (funcData.status == 'complete') {
                            DupliJs.ImportManager.onFileComplete(null, uploadUrl, result);
                            DupliJs.ImportManager.onComplete(null);
                        } else {
                            DupliJs.ImportManager.updateProgress(funcData.remoteChunk.offset, funcData.remoteChunk.fullSize);

                            // Update filename display with real archive name from cloud processing
                            if (funcData.remoteChunk.extraData && funcData.remoteChunk.extraData.archiveName) {
                                DupliJs.ImportManager.packageRowUploading.find('.name .text').text(funcData.remoteChunk.extraData.archiveName);
                            }

                            DupliJs.ImportManager.remoteUploadCall(uploadUrl, funcData.remoteChunk);
                        }
                        return '';
                    },
                    function(result, data, funcData, textStatus, jqXHR) {
                        DupliJs.ImportManager.uploadError(result.data.message);
                        DupliJs.ImportManager.onComplete(null);
                        return '';
                    }, {
                        timeout: 30000 // 30 seconds for large cloud file downloads
                    }
                );
            },
            setArchivePassword: function(button) {
                let row = button.closest('.dupli-import-package');
                let archiveFile = row.data('path');
                let password = row.find('.dup-import-archive-password-request .dup-import-archive-password').val();

                DupliJs.Util.ajaxWrapper({
                        action: 'duplicator_import_set_archive_password',
                        nonce: '<?php echo esc_js(wp_create_nonce('duplicator_import_set_archive_password')); ?>',
                        archive: archiveFile,
                        password: password
                    },
                    function(result, data, funcData, textStatus, jqXHR) {
                        DupliJs.ImportManager.packageRowUploading = row;
                        DupliJs.ImportManager.onFileComplete(null, archiveFile, result, false);
                        DupliJs.ImportManager.onComplete(null);
                        return '';
                    },
                    function(result, data, funcData, textStatus, jqXHR) {
                        DupliJs.addAdminMessage(data.message, 'error', {
                            'hideDelay': 5000
                        });
                        return '';
                    }
                );
            },
            initPageButtons: function() {
                $('.dupli-import-view-list').click(function(event) {
                    event.stopPropagation();
                    DupliJs.ImportManager.updateViewMode('<?php echo esc_js(ImportPageController::VIEW_MODE_ADVANCED); ?>');
                });

                $('.dupli-import-view-single').click(function(event) {
                    event.stopPropagation();
                    DupliJs.ImportManager.updateViewMode('<?php echo esc_js(ImportPageController::VIEW_MODE_BASIC); ?>');
                });

                $('#dupli-import-launch-installer-confirm').click(function(event) {
                    event.stopPropagation();
                    DupliJs.ImportManager.confirmLaunchInstaller();
                });

                $('#dupli-import-launch-installer-cancel').click(function(event) {
                    event.stopPropagation();
                    DupliJs.ImportManager.confirmLaunchLink = false;
                    $('#dupli-import-phase-two').addClass('no-display');
                    $('#dupli-import-phase-one').removeClass('no-display');
                    return false;
                });

                $('#wpbody-content').each(function() {
                    let tabWrapper = $(this);

                    tabWrapper.find('[data-tab-target]').click(function() {
                        let targetId = $(this).data('tab-target');
                        tabWrapper.find('[data-tab-target]').removeClass('active');
                        tabWrapper.find('.tab-content').addClass('no-display');

                        $(this).addClass('active');
                        tabWrapper.find('#' + targetId).removeClass('no-display');
                    });
                });
            },
            confirmLaunchInstaller: function() {
                window.location.href = DupliJs.ImportManager.confirmLaunchLink;
                return false;
            },
            onStart: function(e, files) {
                DupliJs.ImportManager.uploaderDisabler.show();
                DupliJs.ImportManager.startUpload = true;
                DupliJs.ImportManager.uploader.upload("disable");
                DupliJs.ImportManager.autoLaunchLink = false;

                let isValidName = true;
                let alreadyExists = false;

                $.each(files, function(index, value) {
                    if (!DupliJs.ImportManager.isValidFileName(value.name)) {
                        isValidName = false;
                    }

                    if (DupliJs.ImportManager.isAlreadyExists(value.name)) {
                        alreadyExists = true;
                    }
                });

                /*if (!isValidName) {
                    <?php $packageInvalidName->showMessage(); ?>
                    DupliJs.ImportManager.abortUpload();
                    return false;
                }*/

                if (alreadyExists) {
                    <?php $packageAlreadyExists->showMessage(); ?>
                    DupliJs.ImportManager.abortUpload();
                    return false;
                }

                return true;
            },
            onComplete: function(e) {
                $('#dupli-import-remote-url').val('');

                if (DupliJs.ImportManager.autoLaunchAfterUpload && DupliJs.ImportManager.autoLaunchLink) {
                    document.location.href = DupliJs.ImportManager.autoLaunchLink;
                }
                DupliJs.ImportManager.checkMaxUploadedFiles();
            },
            onFileStart: function(e, file) {
                DupliJs.ImportManager.resetUploadTimes();
                DupliJs.ImportManager.packagesList.find('.dupli-import-no-package-found').remove();
                DupliJs.ImportManager.packageRowUploading = DupliJs.ImportManager.packageRowTemplate.clone().prependTo(DupliJs.ImportManager.packagesList);

                DupliJs.ImportManager.packageRowUploading.removeAttr('id');
                DupliJs.ImportManager.packageRowUploading.find('.name .text').text(file.name);
                DupliJs.ImportManager.packageRowUploading.find('.size').text(DupliJs.Util.humanFileSize(file.size));
                DupliJs.ImportManager.packageRowUploading.find('.created').html("<i><?php esc_html_e('loading...', 'duplicator-pro'); ?></i>");

                let loader = DupliJs.ImportManager.packageRowUploading.find('.funcs .dupli-loader').removeClass('no-display');
                loader.find('.dupli-meter > span').css('width', '0%');
                loader.find('.text').text('0%');
            },
            onFileProgress: function(e, file, percent, eventObj) {
                let position = 0;
                if ('currentChunk' in file) {
                    position = file.currentChunk * file.chunkSize;
                } else {
                    if (eventObj.lengthComputable) {
                        position = eventObj.loaded || eventObj.position;
                    } else {
                        position = false;
                    }
                }

                DupliJs.ImportManager.updateProgress(position, file.size);
            },
            onFileComplete: function(e, file, response, showMessage = true) {
                let result = null;
                if (typeof response === 'string' || response instanceof String) {
                    result = JSON.parse(response);
                } else {
                    result = response;
                }
                DupliJs.ImportManager.resetUploadTimes();

                if (result.success == false) {
                    DupliJs.ImportManager.uploadError(result.data.message);
                    return;
                }

                DupliJs.ImportManager.packageRowUploading.data('path', result.data.funcData.archivePath);
                if (result.data.funcData.isImportable) {
                    DupliJs.ImportManager.packageRowUploading.addClass('is-importable');
                    DupliJs.ImportManager.packageRowUploading
                        .find('.dupli-import-action-install')
                        .prop('disabled', false)
                        .data('install-url', result.data.funcData.installerPageLink);
                    DupliJs.ImportManager.autoLaunchLink = result.data.funcData.installerPageLink;
                } else {
                    DupliJs.ImportManager.autoLaunchLink = false;
                    DupliJs.ImportManager.packageRowUploading.find('.dupli-import-action-package-detail-toggle').trigger('click');
                }
                DupliJs.ImportManager.packageRowUploading.find('.dupli-import-package-detail').html(result.data.funcData.htmlDetails);
                DupliJs.ImportManager.packageRowUploading.find('.size').text(DupliJs.Util.humanFileSize(result.data.funcData.archiveSize));
                DupliJs.ImportManager.packageRowUploading.find('.created').text(result.data.funcData.created);
                DupliJs.ImportManager.packageRowUploading.find('.funcs .dupli-loader').addClass('no-display');
                DupliJs.ImportManager.packageRowUploading.find('.funcs .actions').removeClass('no-display');
                DupliJs.ImportManager.packageRowUploading = null;
                if (showMessage) {
                    <?php $packageUploaded->showMessage(); ?>
                }
            },
            onFileError: function(e, file, error) {
                if (error === 'abort') {
                    // no message for abort
                    DupliJs.ImportManager.uploadError(null);
                } else if (error === 'size') {
                    DupliJs.ImportManager.uploadError(<?php echo json_encode(__('The file size exceeds the maximum upload limit.', 'duplicator-pro')); ?>);
                } else {
                    DupliJs.ImportManager.uploadError(error);
                }
            },
            getTimeLeft: function(sizeToFinish) {
                if (DupliJs.ImportManager.lastUploadsTimes.length < 2) {
                    return false;
                }
                let pos1 = DupliJs.ImportManager.lastUploadsTimes[0].pos;
                let time1 = DupliJs.ImportManager.lastUploadsTimes[0].time;

                let index = DupliJs.ImportManager.lastUploadsTimes.length - 1
                let pos2 = DupliJs.ImportManager.lastUploadsTimes[index].pos;
                let time2 = DupliJs.ImportManager.lastUploadsTimes[index].time;

                let deltaPos = pos2 - pos1;
                let deltaTime = time2 - time1;

                return deltaTime / deltaPos * sizeToFinish;
            },
            millisecToTime: function(s) {
                if (s <= 0) {
                    return '<?php echo esc_js(__('loading...', 'duplicator-pro')) ?>';
                }

                var ms = s % 1000;
                s = (s - ms) / 1000;
                var secs = s % 60;
                s = (s - secs) / 60;
                var mins = s % 60;
                var hrs = (s - mins) / 60;

                let result = '';
                if (hrs > 0) {
                    result += ' ' + hrs + ' <?php echo esc_js(__('hr', 'duplicator-pro')) ?>';
                }

                if (mins > 0) {
                    result += ' ' + (mins + 1) + ' <?php echo esc_js(__('min', 'duplicator-pro')) ?>';
                    return result;
                }

                return secs + ' <?php echo esc_js(__('sec', 'duplicator-pro')) ?>';
            },
            resetUploadTimes: function() {
                DupliJs.ImportManager.lastUploadsTimes = [];
            },
            addUploadTime: function(postion) {
                if (DupliJs.ImportManager.lastUploadsTimes.length > 20) {
                    DupliJs.ImportManager.lastUploadsTimes.shift();
                }

                DupliJs.ImportManager.lastUploadsTimes.push({
                    'pos': postion,
                    'time': Date.now()
                });
            },
            updateProgress: function(position, total) {
                let percent = 0;

                if (position !== false) {
                    DupliJs.ImportManager.addUploadTime(position);
                    percent = Math.round((position / total) * 100 * 10) / 10;
                    percent = Number.isInteger(percent) ? percent + ".0" : percent;
                }

                DupliJs.ImportManager.packageRowUploading.find('.size').text(DupliJs.Util.humanFileSize(total));
                let timeLeft = DupliJs.ImportManager.getTimeLeft(total - position);
                let loader = DupliJs.ImportManager.packageRowUploading.find('.funcs .dupli-loader');
                loader.find('.dupli-meter > span').css("width", percent + "%");
                loader.find('.text').text(percent + "% - " + DupliJs.ImportManager.millisecToTime(timeLeft));
            },
            updateContentMessage: function(icon, line1, line2) {
                DupliJs.ImportManager.uploaderContent.find('.message').html('<i class="fas ' + icon + ' fa-sm"></i> ' + line1 + '<br>' + line2);
            },
            uploadError: function(message) {
                DupliJs.ImportManager.removeRow(DupliJs.ImportManager.packageRowUploading);
                DupliJs.ImportManager.packageRowUploading = null;

                if (message != null) {
                    DupliJs.addAdminMessage(uploadFileMessageContent, 'error', {
                        'hideDelay': 60000,
                        'updateCallback': function(msgNode) {
                            msgNode.find('.import-upload-error-message').text(message);
                        }
                    });
                }
            },
            isAlreadyExists: function(name) {
                let alreadyExists = false;
                DupliJs.ImportManager.packagesList.find('tbody .name .text').each(function() {
                    if (name === $(this).text()) {
                        alreadyExists = true;
                    }
                });

                return alreadyExists;
            },
            isValidFileName: function(name) {
                if (!name.match(<?php echo wp_json_encode(DUPLICATOR_ARCHIVE_REGEX_PATTERN); ?>)) {
                    return false;
                }
                return true;
            },
            abortUpload: function() {
                try {
                    DupliJs.ImportManager.uploader.upload("abort");
                } catch (err) {
                    // prevent abort error
                }
                DupliJs.ImportManager.removeRow(DupliJs.ImportManager.packageRowUploading);
                DupliJs.ImportManager.packageRowUploading = null;
            },
            removePackage: function() {
                DupliJs.Util.ajaxWrapper({
                        action: 'duplicator_import_package_delete',
                        path: DupliJs.ImportManager.packageRowToDelete.data('path'),
                        nonce: '<?php echo esc_js(wp_create_nonce('duplicator_import_package_delete')); ?>'
                    },
                    function(result, data, funcData, textStatus, jqXHR) {
                        DupliJs.ImportManager.removeRow(DupliJs.ImportManager.packageRowToDelete);
                        <?php $packageRemoved->showMessage(); ?>;
                        return '';
                    }
                );
            },
            removeRow: function(row) {
                if (!row) {
                    return;
                }
                row.fadeOut(
                    'fast',
                    function() {
                        row.remove();
                        if (DupliJs.ImportManager.packagesList.find('.dupli-import-package').length === 0) {
                            DupliJs.ImportManager.packageRowNoFoundTemplate.clone().appendTo(DupliJs.ImportManager.packagesList);
                        }
                        DupliJs.ImportManager.checkMaxUploadedFiles();
                    }
                );
            },
            checkMaxUploadedFiles: function() {
                let limit = 0; // 0 no limit
                let numPackages = $('.packages-list .dupli-import-package').length;

                if ($('#dupli-import-available-packages').hasClass('view-single-item')) {
                    limit = 1;
                }

                if (limit > 0 && numPackages >= limit) {
                    DupliJs.ImportManager.uploaderDisabler.show();
                    DupliJs.ImportManager.uploader.upload("disable");
                } else {
                    DupliJs.ImportManager.uploaderDisabler.hide();
                    DupliJs.ImportManager.uploader.upload("enable");
                }
            },
            disableWrapper: function() {
                DupliJs.ImportManager.uploaderWrapper
            },
            updateViewMode: function(viewMode) {
                DupliJs.Util.ajaxWrapper({
                        action: 'duplicator_import_set_view_mode',
                        nonce: '<?php echo esc_js(wp_create_nonce('duplicator_import_set_view_mode')); ?>',
                        view_mode: viewMode
                    },
                    function(result, data, funcData, textStatus, jqXHR) {
                        switch (funcData) {
                            case '<?php echo esc_js(ImportPageController::VIEW_MODE_ADVANCED); ?>':
                                $('.dupli-import-view-single').removeClass('active');
                                $('.dupli-import-view-list').addClass('active');
                                $('#dupli-basic-mode-message').addClass('no-display');
                                DupliJs.ImportManager.packagesAviable.removeClass('view-single-item').addClass('view-list-item');
                                break;
                            case '<?php echo esc_js(ImportPageController::VIEW_MODE_BASIC); ?>':
                                $('.dupli-import-view-list').removeClass('active');
                                $('.dupli-import-view-single').addClass('active');
                                $('#dupli-basic-mode-message').removeClass('no-display');
                                DupliJs.ImportManager.packagesAviable.removeClass('view-list-item').addClass('view-single-item');
                                break;
                            default:
                                throw '<?php echo esc_js(__('Invalid view mode', 'duplicator-pro')); ?>';
                        }
                        DupliJs.ImportManager.checkMaxUploadedFiles();
                        return '';

                    },
                    function(result, data, funcData, textStatus, jqXHR) {
                        DupliJs.addAdminMessage(data.message, 'error', {
                            'hideDelay': 5000
                        });
                        return '';
                    }
                );
            },
            console: function() {
                if (this.debug) {
                    if (arguments.length > 1) {
                        console.log(arguments[0], arguments[1]);
                    } else {
                        console.log(arguments[0]);
                    }
                }
            }
        };

        // wait form stone init, it's not a great method but for now I haven't found a better one.
        window.setTimeout(DupliJs.ImportManager.init, 500);

        $('.dupli-import-box.closable').each(function() {
            let box = $(this);
            let title = $(this).find('.box-title');
            let content = $(this).find('.box-content');

            title.click(function() {
                if (box.hasClass('opened')) {
                    box.removeClass('opened').addClass('closed');
                } else {
                    box.removeClass('closed').addClass('opened');
                }
            });
        });
    });
</script>
