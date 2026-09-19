/* ============================================================
   观鸟在广西 —— 图鉴生境筛选
   纯前端交互，不涉及任何后端请求。
   ============================================================ */

(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function () {
    var row = document.getElementById('gx-filter');
    var grid = document.getElementById('gx-grid');
    if (!row || !grid) { return; }

    var chips = row.getElementsByClassName('chip');
    var cards = grid.getElementsByClassName('bird-card');
    if (!chips.length || !cards.length) { return; }

    function apply(cat) {
      for (var i = 0; i < cards.length; i++) {
        var card = cards[i];
        if (cat === 'all' || card.getAttribute('data-cat') === cat) {
          card.hidden = false;
          /* 被隐藏的卡片不会触发滚动出现动画，这里直接补上，避免筛选后一片空白 */
          card.classList.remove('reveal');
          card.classList.add('in');
        } else {
          card.hidden = true;
        }
      }
    }

    row.addEventListener('click', function (e) {
      var el = e.target || e.srcElement;

      /* 点到的可能是 chip 里的计数 span，向上找到按钮本身 */
      while (el && el !== row && String(el.className).indexOf('chip') === -1) {
        el = el.parentNode;
      }
      if (!el || el === row) { return; }

      var cat = el.getAttribute('data-cat') || 'all';

      for (var i = 0; i < chips.length; i++) {
        chips[i].className = (chips[i] === el) ? 'chip on' : 'chip';
      }

      apply(cat);
    });
  });
})();
