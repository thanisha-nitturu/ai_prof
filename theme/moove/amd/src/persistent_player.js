define(['jquery'], function($) {
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .podcast-button-wrapper {
                width: 100% !important;
                text-align: center !important;
                margin-top: 8px !important;
            }

            .podcast-play-btn {
                display: inline-block !important;
                margin: 0 auto !important;
            }

            /* Hide original audio elements */
            .modtype_label audio.podcast-hidden-audio {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                position: absolute !important;
                left: -9999px !important;
            }
        `)
        .appendTo('head');

    var PersistentPlayer = {
        audioElement: null,
        isPlaying: false,
        trackList: [],
        trackIndex: -1,
        currentCourse: '',
        currentTrackIndex: -1,
        activeInlineButton: null,

        init: function() {
            this.audioElement = document.getElementById('audio-element');
            if (!this.audioElement) {
                return;
            }

            this.volumeSlider = document.getElementById('volume');
            this.volumeIcons = {
                mute: document.getElementById('vol-mute'),
                low: document.getElementById('vol-low'),
                medium: document.getElementById('vol-medium'),
                high: document.getElementById('vol-high')
            };

            // Restore from localStorage
            const savedVolume = parseFloat(localStorage.getItem('playerVolume'));
            const savedMuted = localStorage.getItem('playerMuted') === 'true';

            if (!isNaN(savedVolume)) {
                this.audioElement.volume = savedVolume;
                if (this.volumeSlider) {
                    this.volumeSlider.value = savedVolume;
                }
            }

            this.audioElement.muted = savedMuted;
            this.lastVolume = this.audioElement.volume;

            // Volume change listener
            this.audioElement.addEventListener('volumechange', () => {
                const currentVolume = this.audioElement.muted ? 0 : this.audioElement.volume;
                this.updateVolumeIcon(currentVolume);
                if (this.volumeSlider) {
                    this.volumeSlider.value = currentVolume;
                }
                localStorage.setItem('playerVolume', this.audioElement.volume);
                localStorage.setItem('playerMuted', this.audioElement.muted ? 'true' : 'false');
            });

            // Icon click listeners
            ['mute', 'low', 'medium', 'high'].forEach(level => {
                const icon = this.volumeIcons[level];
                if (!icon) {
                    return;
                }
                icon.addEventListener('click', () => {
                    const wasMuted = this.audioElement.muted || this.audioElement.volume === 0;
                    if (wasMuted) {
                        this.audioElement.muted = false;
                        const resumeVolume = this.lastVolume || 0.5;
                        this.audioElement.volume = resumeVolume;
                    } else {
                        this.lastVolume = this.audioElement.volume;
                        this.audioElement.muted = true;
                    }
                });
            });

            // Volume slider listener
            if (this.volumeSlider) {
                this.volumeSlider.addEventListener('input', (e) => {
                    const volume = parseFloat(e.target.value);
                    this.audioElement.volume = volume;
                    this.audioElement.muted = volume === 0;
                });
            }

            this.setupEventListeners();
            this.restorePlayerState();
            this.addPlayButtonsToContent();
            this.adjustPlayerForSidebar();

            // Sidebar observer
            const sidebars = document.querySelectorAll('.drawer-left');
            sidebars.forEach((sidebar) => {
                const observer = new MutationObserver(this.adjustPlayerForSidebar.bind(this));
                observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
            });
        },

        updateVolumeIcon: function(volume) {
            const icons = this.volumeIcons;
            Object.values(icons).forEach(icon => {
                if (icon) {
                    icon.style.display = "none";
                }
            });

            if (this.audioElement.muted || volume === 0) {
                if (icons.mute) {
                    icons.mute.style.display = "inline-block";
                }
            } else if (volume > 0 && volume <= 0.33) {
                if (icons.low) {
                    icons.low.style.display = "inline-block";
                }
            } else if (volume > 0.33 && volume <= 0.66) {
                if (icons.medium) {
                    icons.medium.style.display = "inline-block";
                }
            } else {
                if (icons.high) {
                    icons.high.style.display = "inline-block";
                }
            }
        },

        setupEventListeners: function() {
            var self = this;

            this.updateVolumeBars(this.audioElement.volume);

            $('#volume').on('input', function() {
                const volume = parseFloat($(this).val());
                self.audioElement.volume = volume;
                self.updateVolumeBars(volume);
            });

            $('#play-pause-btn').on('click', function() {
                self.togglePlayPause();
            });

            $('#close-player').on('click', function() {
                self.closePlayer();
            });

            $('#prev-podcast').on('click', function() {
                self.playPrevious();
            });

            $('#next-podcast').on('click', function() {
                self.playNext();
            });

            this.audioElement.addEventListener('loadedmetadata', function() {
                self.updateTotalTime();
            });

            this.audioElement.addEventListener('timeupdate', function() {
                self.updateProgress();
                self.savePlayerState();
            });

            this.audioElement.addEventListener('ended', function() {
                self.onAudioEnded();
            });

            $('#skip-forward').on('click', function() {
                if (self.audioElement.duration) {
                    self.audioElement.currentTime = Math.min(self.audioElement.duration, self.audioElement.currentTime + 10);
                }
            });

            $('#skip-back').on('click', function() {
                if (self.audioElement.duration) {
                    self.audioElement.currentTime = Math.max(0, self.audioElement.currentTime - 10);
                }
            });

            $('#speed').on('change', function() {
                self.audioElement.playbackRate = parseFloat($(this).val());
            });

            $('#seekbar').on('input', function() {
                var percent = parseFloat($(this).val());
                if (self.audioElement.duration) {
                    self.audioElement.currentTime = self.audioElement.duration * (percent / 100);
                }
            });
        },

        ensureTrackListAvailable: function() {
            if (this.trackList.length === 0) {
                const state = localStorage.getItem('moodlePersistentAudio');
                if (state) {
                    try {
                        const parsedState = JSON.parse(state);
                        if (parsedState.trackList && parsedState.trackList.length > 0) {
                            this.trackList = parsedState.trackList;
                            this.currentCourse = parsedState.courseName || '';
                            return true;
                        }
                    } catch (e) {
                        return false;
                    }
                }
                return false;
            }
            return true;
        },

        updateTrackTitleWithRetry: function(courseName, moduleName) {
            let attempts = 0;
            const maxAttempts = 50;
            const interval = setInterval(function() {
                const $title = $('#track-title');
                if ($title.length > 0) {
                    $title.html(
                        courseName + (moduleName ? `<br><span id="module-title">${moduleName}</span>` : '')
                    );
                    clearInterval(interval);
                } else {
                    attempts++;
                    if (attempts > maxAttempts) {
                        clearInterval(interval);
                    }
                }
            }, 100);
        },

        updateAllInlineButtonsUI: function() {
            const self = this;
            $('.podcast-play-btn').each(function() {
                $(this).html(`
                    <span class="button-inner" style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                        <span class="btn-icon play-icon"></span>
                        <span class="btn-text">listen podcast</span>
                    </span>
                `);
            });

            if (self.currentTrackIndex >= 0 && self.currentTrackIndex < self.trackList.length) {
                const currentTrack = self.trackList[self.currentTrackIndex];
                $('.podcast-play-btn').each(function() {
                    const $btn = $(this);
                    const btnUrl = $btn.attr('data-url');
                    if (btnUrl && currentTrack.url.includes(btnUrl)) {
                        self.activeInlineButton = $btn;
                        self.updateSingleInlineButtonUI($btn, self.isPlaying);
                        return false;
                    }
                });
            }
        },

        playTrack: function(index) {
            if (index < 0 || index >= this.trackList.length) {
                return;
            }

            this.currentTrackIndex = index;
            const track = this.trackList[index];
            $('#persistent-audio-player').show();
            $('#track-title').html(`${track.course}<br><span id="module-title">${track.module}</span>`);
            this.audioElement.src = track.url;
            localStorage.setItem('moodleCourseName', track.course);
            localStorage.setItem('moodleModuleName', track.module);

            this.audioElement.play();
            this.isPlaying = true;
            $('#play-pause-icon').removeClass('play-icon').addClass('pause-icon');

            this.updateAllInlineButtonsUI();
            this.savePlayerState(track.title, track.url, track.course, track.module);
        },

        playPrevious: function() {
            if (!this.ensureTrackListAvailable()) {
                return;
            }
            if (this.trackList.length > 0 && this.currentTrackIndex > 0) {
                this.playTrack(this.currentTrackIndex - 1);
            }
        },

        playNext: function() {
            if (!this.ensureTrackListAvailable()) {
                return;
            }
            if (this.trackList.length > 0 && this.currentTrackIndex < this.trackList.length - 1) {
                this.playTrack(this.currentTrackIndex + 1);
            }
        },

        updateSingleInlineButtonUI: function($button, isPlaying) {
            if (!$button) {
                return;
            }
            $button.html(`
                <span class="button-inner" style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <span class="btn-icon ${isPlaying ? 'pause-icon' : 'play-icon'}"></span>
                    <span class="btn-text">listen podcast</span>
                </span>
            `);
        },

        addPlayButtonsToContent: function() {
            var self = this;
            var newTrackList = [];
            var courseName = $('.page-header-headings h1.h2').first().text().trim();

            $('li.activity.modtype_label').each(function(index) {
                var $label = $(this);
                if ($label.find('.podcast-play-btn').length > 0) {
                    return;
                }

                var $audio = $label.find('audio');
                if ($audio.length === 0) {
                    return;
                }

                var $source = $audio.find('source');
                if ($source.length === 0) {
                    return;
                }

                var audioSrc = $source.attr('src');
                var title = $label.find('.activity-title, .instancename').text().trim() || 'Label Audio ' + (index + 1);
                var moduleName = $label.closest('li.section.main').find('h3.sectionname a').text().trim();

                if (!audioSrc) {
                    return;
                }

                // Hide the original audio element
                $audio.addClass('podcast-hidden-audio');

                var track = {
                    title: title,
                    url: audioSrc,
                    course: courseName,
                    module: moduleName
                };
                newTrackList.push(track);

                var playButtonWrapper = $('<div>')
                    .addClass('custom-podcast-button-wrapper')
                    .css({
                        marginTop: '10px',
                        display: 'flex',
                        justifyContent: 'center',
                        alignItems: 'center',
                        width: '100%'
                    });

                var playButton = $('<button>')
                    .addClass('podcast-play-btn')
                    .attr('data-url', audioSrc)
                    .css({
                        background: '#377BFB',
                        color: 'white',
                        border: 'none',
                        padding: '6px 12px',
                        borderRadius: '4px',
                        cursor: 'pointer',
                        fontSize: '14px'
                    })
                    .html(`<span class="button-inner">
                        <span class="btn-icon play-icon"></span>
                        <span class="btn-text">listen podcast</span>
                    </span>`)
                    .on('click', function() {
                        if (self.currentCourse !== courseName) {
                            self.trackList = newTrackList;
                            self.currentCourse = courseName;
                        }

                        const indexInList = self.trackList.findIndex(t => t.url === audioSrc);
                        if (indexInList === -1) {
                            return;
                        }

                        const isSameTrack = self.currentTrackIndex === indexInList;
                        if (isSameTrack) {
                            if (self.isPlaying) {
                                self.togglePlayPause();
                            } else {
                                self.audioElement.play();
                                self.isPlaying = true;
                                $('#play-pause-icon').removeClass('play-icon').addClass('pause-icon');
                                self.updateSingleInlineButtonUI($(this), true);
                                self.savePlayerState();
                            }
                        } else {
                            self.playTrack(indexInList);
                        }

                        window.lastClickedPodcastButton = this;
                    });

                playButtonWrapper.append(playButton);
                $label.find('.activity-altcontent, .contentwithoutlink').first().html(playButtonWrapper);
            });

            if (newTrackList.length > 0) {
                this.trackList = newTrackList;
                this.currentCourse = courseName;
            }
        },

        togglePlayPause: function() {
            if (!this.audioElement.src) {
                return;
            }

            const $icon = $('#play-pause-icon');

            if (this.isPlaying) {
                this.audioElement.pause();
                this.isPlaying = false;
                $icon.removeClass('pause-icon').addClass('play-icon');
            } else {
                this.audioElement.play();
                this.isPlaying = true;
                $icon.removeClass('play-icon').addClass('pause-icon');
            }

            if (this.activeInlineButton) {
                this.updateSingleInlineButtonUI(this.activeInlineButton, this.isPlaying);
            }

            this.savePlayerState();
        },

        closePlayer: function() {
            this.audioElement.pause();
            this.isPlaying = false;
            this.audioElement.src = '';
            $('#persistent-audio-player').hide();
            $('#play-pause-icon').removeClass('pause-icon').addClass('play-icon');
            this.currentTrackIndex = -1;
            this.activeInlineButton = null;

            this.updateAllInlineButtonsUI();
            this.clearPlayerState();
        },

        updateTotalTime: function() {
            var duration = this.audioElement.duration;
            if (duration) {
                $('#total-time').text(this.formatTime(duration));
            }
        },

        updateProgress: function() {
            var current = this.audioElement.currentTime;
            var duration = this.audioElement.duration;
            $('#current-time').text(this.formatTime(current));
            if (duration > 0) {
                var percentage = (current / duration) * 100;
                $('#seekbar').val(percentage);
            }
        },

        onAudioEnded: function() {
            this.isPlaying = false;
            $('#play-pause-icon').removeClass('pause-icon').addClass('play-icon');

            if (this.activeInlineButton) {
                this.updateSingleInlineButtonUI(this.activeInlineButton, false);
            }
        },

        savePlayerState: function(title, url, courseName, moduleName) {
            var state = {
                title: title || '',
                url: url || this.audioElement.src,
                currentTime: this.audioElement.currentTime,
                isPlaying: this.isPlaying,
                visible: $('#persistent-audio-player').is(':visible'),
                volume: this.audioElement.volume,
                rate: this.audioElement.playbackRate,
                courseName: courseName || '',
                moduleName: moduleName || '',
                trackList: this.trackList,
                currentTrackIndex: this.currentTrackIndex,
                currentCourse: this.currentCourse
            };
            localStorage.setItem('moodlePersistentAudio', JSON.stringify(state));
        },

        restorePlayerState: function() {
            var state = localStorage.getItem('moodlePersistentAudio');
            if (!state) {
                return;
            }

            try {
                state = JSON.parse(state);
            } catch (e) {
                return;
            }

            if (!state.url) {
                return;
            }

            var self = this;

            const savedMuted = localStorage.getItem('playerMuted') === 'true';
            const savedVolume = parseFloat(localStorage.getItem('playerVolume'));
            const volumeToApply = !isNaN(savedVolume) ? savedVolume : 0.5;

            this.audioElement.muted = savedMuted;
            this.audioElement.volume = volumeToApply;
            this.lastVolume = volumeToApply;

            $('#volume').val(savedMuted ? 0 : volumeToApply);
            this.updateVolumeIcon(savedMuted ? 0 : volumeToApply);

            if (state.visible) {
                $('#persistent-audio-player').show();
                if ($('#track-title').length === 0) {
                    $('#persistent-audio-player').prepend('<div id="track-title" style="padding: 8px; font-weight: bold;"></div>');
                }
            } else {
                $('#persistent-audio-player').hide();
                return;
            }

            var courseName = state.courseName ||
                localStorage.getItem('moodleCourseName') ||
                $('.page-header-headings h1.h2').first().text().trim();

            var moduleName = state.moduleName || localStorage.getItem('moodleModuleName') || '';

            this.updateTrackTitleWithRetry(courseName, moduleName);

            this.trackList = state.trackList || [];
            this.currentTrackIndex = typeof state.currentTrackIndex === 'number' ? state.currentTrackIndex : -1;
            this.currentCourse = state.currentCourse || '';

            this.audioElement.src = state.url;
            this.audioElement.playbackRate = state.rate || 1;
            $('#speed').val(this.audioElement.playbackRate);

            this.audioElement.addEventListener('loadedmetadata', function onMeta() {
                self.audioElement.removeEventListener('loadedmetadata', onMeta);
                self.audioElement.currentTime = state.currentTime || 0;

                if (state.isPlaying) {
                    self.audioElement.play().then(function() {
                        self.isPlaying = true;
                        $('#play-pause-icon').removeClass('play-icon').addClass('pause-icon');
                        self.updateAllInlineButtonsUI();
                    }).catch(function() {
                        self.isPlaying = false;
                        $('#play-pause-icon').removeClass('pause-icon').addClass('play-icon');
                        self.updateAllInlineButtonsUI();
                    });
                } else {
                    self.isPlaying = false;
                    $('#play-pause-icon').removeClass('pause-icon').addClass('play-icon');
                    self.updateAllInlineButtonsUI();
                }
            }, {once: true});
        },

        clearPlayerState: function() {
            localStorage.removeItem('moodlePersistentAudio');
        },

        adjustPlayerForSidebar: function() {
            var player = document.getElementById('persistent-audio-player');
            var sidebars = document.querySelectorAll('.drawer-left');
            if (!player) {
                return;
            }

            let totalOffset = 0;
            sidebars.forEach((sidebar) => {
                if (sidebar.classList.contains('show')) {
                    const width = sidebar.offsetWidth;
                    if (width && width > 0) {
                        totalOffset += width;
                    }
                }
            });

            player.style.left = totalOffset + 'px';
            player.style.width = `calc(100% - ${totalOffset}px)`;
        },

        formatTime: function(seconds) {
            var hrs = Math.floor(seconds / 3600);
            var mins = Math.floor((seconds % 3600) / 60);
            var secs = Math.floor(seconds % 60);

            if (hrs > 0) {
                return `${hrs}:${mins < 10 ? '0' : ''}${mins}:${secs < 10 ? '0' : ''}${secs}`;
            }
            return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
        },

        updateVolumeBars: function(volume) {
            const slider = document.getElementById('volume');
            if (slider) {
                slider.value = volume;
            }
        }
    };

    return PersistentPlayer;
});