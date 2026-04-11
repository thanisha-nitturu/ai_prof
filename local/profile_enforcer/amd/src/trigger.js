/**
 * Profile submission trigger for Veda integration.
 *
 * @module     local_profile_enforcer/trigger
 * @copyright  2026 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function ($) {
    return {
        init: function () {
            // Target the profile edit form
            const $form = $('form.mform');

            if (!$form.length) {
                return;
            }

            // We use the submit event directly - this is synchronous and stable
            $form.on('submit', function () {
                // Get name details
                const firstname = $('#id_firstname').val() || '';
                const lastname = $('#id_lastname').val() || '';
                const fullname = (firstname + ' ' + lastname).trim();
                const email = $('#id_email').val() || '';

                /**
                 * Helper to convert an image source to base64.
                 * @param {string} src
                 * @returns {string}
                 */
                const getBase64 = (src) => {
                    if (!src || src.indexOf('data:') === 0) {
                        return src || '';
                    }

                    // Method 1: Try Canvas (fastest for already loaded images/blobs)
                    try {
                        const img = $('img[src="' + src + '"]')[0];
                        if (img && (img.naturalWidth || img.width)) {
                            const canvas = document.createElement('canvas');
                            const w = img.naturalWidth || img.width;
                            const h = img.naturalHeight || img.height;
                            canvas.width = w;
                            canvas.height = h;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0);
                            const dataUrl = canvas.toDataURL('image/png');
                            if (dataUrl.length > 100) {
                                return dataUrl;
                            }
                        }
                    } catch (e) {
                        window.console.warn('Veda: Canvas failed, trying XHR', e);
                    }

                    // Method 2: Synchronous XHR (Reliable fallback for same-origin)
                    try {
                        const xhr = new XMLHttpRequest();
                        xhr.open('GET', src, false); // Synchronous
                        xhr.overrideMimeType('text/plain; charset=x-user-defined');
                        xhr.send(null);
                        if (xhr.status === 200) {
                            let binary = '';
                            const responseText = xhr.responseText;
                            for (let i = 0; i < responseText.length; i++) {
                                const code = responseText.charCodeAt(i);
                                // For x-user-defined, we need to mask the high byte (0xF700)
                                // We use subtraction instead of bitwise & to satisfy ESLint
                                binary += String.fromCharCode(code > 255 ? code - 0xF700 : code);
                            }
                            return 'data:image/png;base64,' + btoa(binary);
                        }
                    } catch (e) {
                        window.console.warn('Veda: XHR failed for ' + src, e);
                    }

                    return src;
                };

                // SMART SEARCH: Find the most relevant image
                let imageSrc = '';
                let searchMethod = 'none';

                // 1. Look for new uploads in filepicker/filemanager (img tags)
                const $newImg = $('.fp-thumbnail img, .filemanager img, .file-picker img, .fp-icon img').first();
                if ($newImg.length && $newImg.attr('src')) {
                    imageSrc = getBase64($newImg.attr('src'));
                    searchMethod = 'new_upload_img';
                }

                // 2. Fallback to background images (some themes use them for thumbnails)
                if (!imageSrc || imageSrc.indexOf('data:') !== 0) {
                    const $bgEl = $('.fp-thumbnail, .fp-icon').first();
                    const bg = $bgEl.css('background-image');
                    if (bg && bg !== 'none') {
                        const url = bg.replace(/^url\(["']?/, '').replace(/["']?\)$/, '');
                        imageSrc = getBase64(url);
                        searchMethod = 'new_upload_bg';
                    }
                }

                // 3. Last fallback: Existing profile picture
                if (!imageSrc || imageSrc.indexOf('data:') !== 0) {
                    const $currentImg = $('.userpicture, img.userpicture, .fstatic img, .fitem img').first();
                    if ($currentImg.length && $currentImg.attr('src')) {
                        imageSrc = getBase64($currentImg.attr('src'));
                        searchMethod = 'existing_profile';
                    }
                }

                window.console.log('Veda: Image found via ' + searchMethod);

                // Dispatch the custom event (The Trigger)
                const event = new CustomEvent('veda:profile_submit', {
                    detail: {
                        username: fullname,
                        email: email,
                        image: imageSrc,
                        timestamp: Date.now()
                    }
                });

                // DEBUG ALERT: Show the data for verification
                // const displayImg = imageSrc.startsWith('data:') ?
                // imageSrc.substring(0, 30) + '...' : imageSrc;
                // const alertMsg = "STABLE TRIGGER FIRED!\n\nName: " + fullname +
                //     "\nEmail: " + email + "\nImage: " + displayImg;
                // alert(alertMsg);
                window.dispatchEvent(event);

                // Since this is a fast, synchronous event, we don't need preventDefault()
                // This prevents the "Unsaved changes" warning from appearing.
            });
        }
    };
});
