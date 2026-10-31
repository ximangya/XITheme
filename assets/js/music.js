/**
 * 小型音乐播放器交互
 */
(function () {
  'use strict';

  var root = document.getElementById('xi-music');
  if (!root) return;

  var audio = root.querySelector('.xi-music-audio');
  var toggle = root.querySelector('[data-music-toggle]');
  var close = root.querySelector('[data-music-close]');
  var mini = root.querySelector('[data-music-mini]');
  var progress = root.querySelector('[data-music-progress]');
  var fill = root.querySelector('.xi-music-fill');
  var currentEl = root.querySelector('[data-music-current]');
  var durationEl = root.querySelector('[data-music-duration]');
  if (!audio || !toggle || !progress) return;

  var format = function (seconds) {
    if (!isFinite(seconds) || seconds < 0) seconds = 0;
    var h = Math.floor(seconds / 3600);
    var m = Math.floor(seconds % 3600 / 60);
    var s = Math.floor(seconds % 60);
    return (h ? h + ':' + (m < 10 ? '0' : '') : '') + m + ':' + (s < 10 ? '0' : '') + s;
  };

  var update = function () {
    var duration = audio.duration;
    if (!isFinite(duration) || duration <= 0) {
      fill.style.width = '0%';
      progress.setAttribute('aria-valuenow', '0');
      durationEl.textContent = '0:00';
      currentEl.textContent = '0:00';
      return;
    }
    var percent = Math.max(0, Math.min(100, audio.currentTime / duration * 100));
    fill.style.width = percent + '%';
    progress.setAttribute('aria-valuenow', String(Math.round(percent)));
    progress.setAttribute('aria-valuetext', format(audio.currentTime) + ' / ' + format(duration));
    currentEl.textContent = format(audio.currentTime);
    durationEl.textContent = format(duration);
  };

  var setPlaying = function (on) {
    root.classList.toggle('is-playing', !!on);
    toggle.setAttribute('aria-label', on ? '暂停' : '播放');
  };

  toggle.addEventListener('click', function () {
    if (audio.paused) {
      var promise = audio.play();
      if (promise && promise.catch) promise.catch(function () {});
    } else {
      audio.pause();
    }
  });

  audio.addEventListener('play', function () { setPlaying(true); });
  audio.addEventListener('pause', function () { setPlaying(false); });
  audio.addEventListener('timeupdate', update);
  audio.addEventListener('durationchange', update);
  audio.addEventListener('loadedmetadata', update);
  audio.addEventListener('canplay', update);
  audio.addEventListener('ended', function () { setPlaying(false); update(); });
  audio.addEventListener('error', function () { setPlaying(false); });

  var volume = parseInt(root.getAttribute('data-volume') || '55', 10);
  if (!isNaN(volume)) audio.volume = Math.max(0, Math.min(1, volume / 100));

  var seekAt = function (clientX) {
    var duration = audio.duration;
    if (!isFinite(duration) || duration <= 0) return;
    var rect = progress.getBoundingClientRect();
    var ratio = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
    audio.currentTime = ratio * duration;
    update();
  };

  progress.addEventListener('pointerdown', function (e) {
    if (progress.setPointerCapture) {
      try { progress.setPointerCapture(e.pointerId); } catch (err) {}
    }
    seekAt(e.clientX);
  });
  progress.addEventListener('pointermove', function (e) {
    if (e.buttons) seekAt(e.clientX);
  });
  progress.addEventListener('keydown', function (e) {
    var duration = audio.duration;
    if (!isFinite(duration) || duration <= 0) return;
    var step = e.shiftKey ? 10 : 5;
    if (e.key === 'ArrowRight') {
      e.preventDefault();
      audio.currentTime = Math.min(duration, audio.currentTime + step);
    } else if (e.key === 'ArrowLeft') {
      e.preventDefault();
      audio.currentTime = Math.max(0, audio.currentTime - step);
    } else if (e.key === 'Home') {
      e.preventDefault();
      audio.currentTime = 0;
    } else if (e.key === 'End') {
      e.preventDefault();
      audio.currentTime = duration;
    } else {
      return;
    }
    update();
  });

  if (close) {
    close.addEventListener('click', function () {
      root.classList.add('is-collapsed');
      if (mini) mini.focus();
    });
  }
  if (mini) {
    mini.addEventListener('click', function () {
      root.classList.remove('is-collapsed');
      toggle.focus();
    });
  }

  root.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !root.classList.contains('is-collapsed') && close) {
      close.click();
    }
  });

  if (root.getAttribute('data-autoplay') === '1') {
    var auto = audio.play();
    if (auto && auto.catch) auto.catch(function () {});
  }

  // 脚本在 audio 标签之后加载，metadata 可能已经就绪，不会再触发 loadedmetadata
  if (audio.readyState >= 1) {
    update();
  } else {
    audio.addEventListener('loadedmetadata', update);
  }
})();
