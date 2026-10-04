/* ============================================================
   用户中心（仪表盘）脚本
   ============================================================ */

(function () {
  'use strict';

  var loading = document.getElementById('profile-loading');
  var content = document.getElementById('profile-content');

  function render(state) {
    var name = String(state.username == null ? '' : state.username);
    var role = state.role || '';

    document.getElementById('pf-avatar').textContent = name.charAt(0) || '?';
    document.getElementById('pf-name').textContent = name;
    document.getElementById('pf-role').textContent = role;

    var iu = document.getElementById('info-username');
    var ir = document.getElementById('info-role');
    if (iu) { iu.textContent = name; }
    if (ir) { ir.textContent = state.is_admin ? '站点管理员' : '注册志愿者'; }

    var flagArea = document.getElementById('flag-area');
    if (state.is_admin && state.flag) {
      flagArea.innerHTML =
        '<div class="flag-box">' +
        '  <h3>管理员专属信息</h3>' +
        '  <div class="flag-value" id="flag-value"></div>' +
        '  <p class="note">该信息仅对站点管理员账号可见，请勿外传。</p>' +
        '</div>';
      document.getElementById('flag-value').textContent = state.flag;
      WF.toast('已加载管理员专属信息', 'ok');
    } else {
      flagArea.innerHTML =
        '<div class="alert alert-info">' +
        '当前账号为普通志愿者，暂无管理员权限。若你是站点管理员，请使用管理员账号登录。' +
        '</div>';
    }

    if (loading) { loading.style.display = 'none'; }
    if (content) { content.style.display = 'block'; }
  }

  /* ---------------- 加载账号信息 ---------------- */

  WF.apiGet('profile.php').then(function (r) {
    if (!r || !r.logged_in) {
      window.location.href = 'login.html';
      return;
    }
    render(r);
  });

  /* ---------------- 尚未开放的入口 ---------------- */

  var soon = document.querySelectorAll('[data-soon]');
  for (var i = 0; i < soon.length; i++) {
    (function (el) {
      el.addEventListener('click', function () {
        WF.toast(el.getAttribute('data-soon') || '功能开发中', 'err');
      });
    })(soon[i]);
  }

  /* ---------------- 退出登录 ---------------- */

  var out = document.getElementById('logout-btn');
  if (out) {
    out.addEventListener('click', function () {
      out.disabled = true;
      WF.api('logout.php', {}).then(function () {
        window.location.href = 'index.html';
      });
    });
  }
})();
