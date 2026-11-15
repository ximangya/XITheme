/**
 * 幸运大转盘前端
 */
(function () {
  'use strict';

  var config = window.xiWheel || {};
  var canvas = document.getElementById('xi-wheel-canvas');
  if (!canvas) return;

  var ctx = canvas.getContext('2d');
  var optionsEl = document.getElementById('xi-wheel-options');
  var spinBtn = document.getElementById('xi-wheel-spin');
  var spinLabel = document.getElementById('xi-wheel-spin-label');
  var addBtn = document.getElementById('xi-wheel-add');
  var resetBtn = document.getElementById('xi-wheel-reset');
  var countEl = document.getElementById('xi-wheel-count');
  var modal = document.getElementById('xi-wheel-modal');
  var prizeEl = document.getElementById('xi-wheel-prize');
  var againBtn = document.getElementById('xi-wheel-again');
  if (!ctx || !optionsEl || !spinBtn || !modal) return;

  var FALLBACK = [
    { name: '一等奖', color: '#ff6b6b' },
    { name: '二等奖', color: '#feca57' },
    { name: '三等奖', color: '#48dbfb' },
    { name: '谢谢参与', color: '#ff9ff3' },
    { name: '再来一次', color: '#1dd1a1' },
    { name: '幸运奖', color: '#5f27cd' }
  ];
  var PALETTE = ['#6c5ce7', '#00b894', '#e17055', '#0984e3', '#d63031', '#fdcb6e', '#00cec9', '#e84393'];
  var DEFAULTS = Array.isArray(config.items) && config.items.length >= 2 ? config.items : FALLBACK;
  var STORAGE_KEY = config.storageKey || 'xi_wheel_items_v1';
  var AJAX_URL = config.ajax || '';
  var NONCE = config.nonce || '';
  var SOUND = !(config.sound === false || config.sound === 0 || config.sound === '0' || config.sound === '');
  var CONFETTI = !(config.confetti === false || config.confetti === 0 || config.confetti === '0' || config.confetti === '');

  var items = loadItems();
  var rotation = 0;
  var spinning = false;
  var audioCtx = null;

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function normalizeItem(item, index) {
    var name = item && item.name ? String(item.name).trim() : '';
    if (!name) name = '选项' + (index + 1);
    name = name.slice(0, 12);
    var color = item && item.color ? String(item.color) : '';
    if (/^#[0-9a-f]{3}$/i.test(color)) {
      color = '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3];
    }
    if (!/^#[0-9a-f]{6}$/i.test(color)) color = PALETTE[index % PALETTE.length];
    return { name: name, color: color };
  }

  function loadItems() {
    try {
      var saved = localStorage.getItem(STORAGE_KEY);
      if (saved) {
        var arr = JSON.parse(saved);
        if (Array.isArray(arr) && arr.length >= 2) {
          return arr.map(normalizeItem);
        }
      }
    } catch (err) {}
    return DEFAULTS.map(normalizeItem);
  }

  function saveItems() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch (err) {}
  }

  function renderList() {
    optionsEl.innerHTML = '';
    items.forEach(function (item, index) {
      var row = document.createElement('div');
      row.className = 'xi-wheel-option';

      var idx = document.createElement('span');
      idx.className = 'xi-wheel-index';
      idx.textContent = String(index + 1);

      var name = document.createElement('input');
      name.type = 'text';
      name.className = 'xi-wheel-name';
      name.value = item.name;
      name.maxLength = 12;
      name.placeholder = '选项名称';
      name.setAttribute('aria-label', '选项 ' + (index + 1) + ' 名称');
      name.addEventListener('input', function () {
        items[index].name = name.value;
        saveItems();
        drawWheel();
      });

      var color = document.createElement('input');
      color.type = 'color';
      color.className = 'xi-wheel-color';
      color.value = item.color;
      color.setAttribute('aria-label', '选项 ' + (index + 1) + ' 颜色');
      color.addEventListener('input', function () {
        items[index].color = color.value;
        saveItems();
        drawWheel();
      });

      var del = document.createElement('button');
      del.type = 'button';
      del.className = 'xi-wheel-del';
      del.textContent = '×';
      del.title = '删除';
      del.setAttribute('aria-label', '删除选项 ' + (index + 1));
      del.disabled = items.length <= 2;
      del.addEventListener('click', function () {
        if (items.length <= 2) return;
        items.splice(index, 1);
        saveItems();
        renderList();
        drawWheel();
      });

      row.appendChild(idx);
      row.appendChild(name);
      row.appendChild(color);
      row.appendChild(del);
      optionsEl.appendChild(row);
    });
    countEl.textContent = items.length + ' 项';
  }

  function drawWheel() {
    var width = canvas.width;
    var height = canvas.height;
    var cx = width / 2;
    var cy = height / 2;
    var radius = width / 2 - 10;
    var count = items.length;
    var arc = Math.PI * 2 / count;

    ctx.clearRect(0, 0, width, height);
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(rotation * Math.PI / 180);

    for (var i = 0; i < count; i++) {
      var start = i * arc;
      var end = start + arc;
      ctx.beginPath();
      ctx.moveTo(0, 0);
      ctx.arc(0, 0, radius, start, end);
      ctx.closePath();
      ctx.fillStyle = items[i].color || PALETTE[i % PALETTE.length];
      ctx.fill();
      ctx.strokeStyle = 'rgba(255,255,255,.85)';
      ctx.lineWidth = 3;
      ctx.stroke();
    }

    ctx.beginPath();
    ctx.arc(0, 0, radius, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(255,255,255,.9)';
    ctx.lineWidth = 6;
    ctx.stroke();

    ctx.textBaseline = 'middle';
    var fontSize = count <= 6 ? 40 : (count <= 10 ? 32 : 24);
    ctx.font = 'bold ' + fontSize + 'px "PingFang SC","Microsoft YaHei",sans-serif';
    var labelStart = radius * 0.28;
    for (var j = 0; j < count; j++) {
      var mid = j * arc + arc / 2;
      var flip = Math.cos(mid) < 0;
      ctx.save();
      if (flip) {
        // 左半边翻转 180°，保证文字正向可读
        ctx.rotate(mid + Math.PI);
        ctx.translate(-labelStart, 0);
        ctx.textAlign = 'right';
      } else {
        ctx.rotate(mid);
        ctx.translate(labelStart, 0);
        ctx.textAlign = 'left';
      }
      ctx.fillStyle = '#fff';
      ctx.shadowColor = 'rgba(0,0,0,.4)';
      ctx.shadowBlur = 4;
      ctx.fillText(items[j].name, 0, 0);
      ctx.restore();
    }

    ctx.restore();

    ctx.beginPath();
    ctx.arc(cx, cy, 10, 0, Math.PI * 2);
    ctx.fillStyle = '#f59e0b';
    ctx.fill();
  }

  function ensureAudio() {
    if (!SOUND) return null;
    if (!audioCtx) {
      var AC = window.AudioContext || window.webkitAudioContext;
      if (AC) audioCtx = new AC();
    }
    if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
    return audioCtx;
  }

  function tickSound() {
    var ac = ensureAudio();
    if (!ac) return;
    var o = ac.createOscillator();
    var g = ac.createGain();
    o.type = 'square';
    o.frequency.setValueAtTime(1800, ac.currentTime);
    g.gain.setValueAtTime(0.06, ac.currentTime);
    g.gain.exponentialRampToValueAtTime(0.0001, ac.currentTime + 0.05);
    o.connect(g);
    g.connect(ac.destination);
    o.start();
    o.stop(ac.currentTime + 0.06);
  }

  function winSound() {
    var ac = ensureAudio();
    if (!ac) return;
    var notes = [523.25, 659.25, 783.99, 1046.50];
    notes.forEach(function (freq, i) {
      var t = ac.currentTime + i * 0.12;
      var o = ac.createOscillator();
      var g = ac.createGain();
      o.type = 'triangle';
      o.frequency.setValueAtTime(freq, t);
      g.gain.setValueAtTime(0.0001, t);
      g.gain.exponentialRampToValueAtTime(0.18, t + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, t + 0.5);
      o.connect(g);
      g.connect(ac.destination);
      o.start(t);
      o.stop(t + 0.55);
    });
  }

  function setSpinning(on) {
    spinning = on;
    spinBtn.disabled = on;
    spinLabel.textContent = on ? '抽奖中' : '开始';
    spinBtn.querySelector('small').textContent = on ? '...' : 'GO';
  }

  async function startSpin() {
    if (spinning) return;
    ensureAudio();
    setSpinning(true);

    try {
      var body = new URLSearchParams();
      body.set('action', 'xi_wheel_spin');
      body.set('nonce', NONCE);
      body.set('items', JSON.stringify(items.map(function (it) { return { name: it.name.trim() || '未命名' }; })));

      var resp = await fetch(AJAX_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body
      });
      var data = await resp.json();
      if (!data || !data.success) {
        throw new Error(data && data.data && data.data.message ? data.data.message : '抽奖失败');
      }

      var result = data.data;
      var count = items.length;
      var seg = 360 / count;
      var winIndex = result.index;
      var centerOffset = -90 - (winIndex * seg + seg / 2);
      var halfSector = seg / 2;
      var margin = Math.max(6, seg * 0.18);
      var jitter = (Math.random() * 2 - 1) * (halfSector - margin);
      var currentMod = ((rotation % 360) + 360) % 360;
      var target = rotation + 5 * 360 + (centerOffset + jitter) - currentMod;
      animateSpin(target, result);
    } catch (err) {
      setSpinning(false);
      alert('抽奖出错：' + err.message);
    }
  }

  function animateSpin(target, data) {
    var start = rotation;
    var delta = target - start;
    var duration = 4600 + Math.random() * 800;
    var startTime = performance.now();
    var lastBoundary = -1;

    function frame(now) {
      var p = Math.min((now - startTime) / duration, 1);
      var eased = cubicBezier(p, 0.12, 0.80, 0.18, 1.0);
      rotation = start + delta * eased;
      drawWheel();

      var count = items.length;
      var seg = 360 / count;
      var pointerAngle = -90 - rotation;
      var boundaryIndex = Math.floor(pointerAngle / seg);
      if (boundaryIndex !== lastBoundary) {
        lastBoundary = boundaryIndex;
        tickSound();
      }

      if (p < 1) {
        requestAnimationFrame(frame);
      } else {
        rotation = target;
        drawWheel();
        winSound();
        setTimeout(function () { showResult(data); }, 350);
      }
    }
    requestAnimationFrame(frame);
  }

  function cubicBezier(t, p1x, p1y, p2x, p2y) {
    function A(a1, a2) { return 1 - 3 * a2 + 3 * a1; }
    function B(a1, a2) { return 3 * a2 - 6 * a1; }
    function C(a1) { return 3 * a1; }
    function calc(a, v) { return ((A(a, a) * v + B(a, a)) * v + C(a)) * v; }
    function slope(a, v) { return 3 * A(a, a) * v * v + 2 * B(a, a) * v + C(a); }
    function getX(v) { return calc(p1x, v); }
    function getY(v) { return calc(p1y, v); }
    var guess = t;
    for (var i = 0; i < 8; i++) {
      var x = getX(guess) - t;
      if (Math.abs(x) < 1e-6) break;
      var d = slope(p1x, guess);
      if (Math.abs(d) < 1e-6) break;
      guess -= x / d;
    }
    return getY(guess);
  }

  function showResult(data) {
    prizeEl.textContent = data.name;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('xi-wheel-modal-open');
    if (CONFETTI) burstConfetti();
    againBtn.focus();
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('xi-wheel-modal-open');
    setSpinning(false);
    spinBtn.focus();
  }

  function burstConfetti() {
    var colors = ['#ff6b6b', '#feca57', '#48dbfb', '#ff9ff3', '#1dd1a1', '#a29bfe', '#fd79a8', '#fdcb6e'];
    var holder = document.createElement('div');
    holder.className = 'xi-wheel-confetti';
    document.body.appendChild(holder);
    for (var i = 0; i < 80; i++) {
      var piece = document.createElement('span');
      piece.style.left = Math.random() * 100 + '%';
      piece.style.background = colors[Math.floor(Math.random() * colors.length)];
      piece.style.animationDuration = (2 + Math.random() * 2) + 's';
      piece.style.animationDelay = (Math.random() * 0.4) + 's';
      piece.style.transform = 'rotate(' + Math.random() * 360 + 'deg)';
      holder.appendChild(piece);
    }
    setTimeout(function () { holder.remove(); }, 4000);
  }

  spinBtn.addEventListener('click', startSpin);
  againBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) {
    if (e.target === modal) closeModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });

  addBtn.addEventListener('click', function () {
    if (items.length >= 24) return;
    var name = '选项' + (items.length + 1);
    items.push({ name: name, color: PALETTE[items.length % PALETTE.length] });
    saveItems();
    renderList();
    drawWheel();
  });

  resetBtn.addEventListener('click', function () {
    if (!confirm('确定恢复默认选项吗？当前配置将被覆盖。')) return;
    items = DEFAULTS.map(normalizeItem);
    saveItems();
    renderList();
    drawWheel();
  });

  renderList();
  drawWheel();
})();
