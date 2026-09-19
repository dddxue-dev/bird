/* ============================================================
   修改密码页脚本
   ============================================================ */

(function () {
  'use strict';

  function showAlert(text, type) {
    var box = document.getElementById('form-alert');
    if (!box) { return; }
    box.className = 'alert alert-' + (type || 'err');
    box.textContent = text;
    box.style.display = 'block';
  }

  function hideAlert() {
    var box = document.getElementById('form-alert');
    if (box) { box.style.display = 'none'; }
  }

  /* ---------------- 载入当前账号 ---------------- */

  WF.apiGet('profile.php').then(function (r) {
    if (!r || !r.logged_in) {
      window.location.href = 'login.html';
      return;
    }
    var el = document.getElementById('ph-username');
    if (el) { el.textContent = String(r.username == null ? '' : r.username); }
  });

  /* ---------------- 提交修改 ---------------- */

  var form = document.getElementById('passchange-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      hideAlert();

      var cur = form.current_password.value;
      var np = form.password.value;
      var rp = form.re_password.value;

      if (!cur || !np) {
        showAlert('请填写当前密码和新密码', 'err');
        return;
      }
      if (np !== rp) {
        showAlert('两次输入的新密码不一致', 'err');
        return;
      }

      WF.withLoading(form.querySelector('button[type=submit]'), function () {
        return WF.api('pass_change.php', {
          current_password: cur, password: np, re_password: rp
        });
      }).then(function (r) {
        if (r.ok) {
          showAlert('密码修改成功，正在退出登录，请用新密码重新登录…', 'ok');
          WF.toast('密码已更新', 'ok');
          form.reset();
          setTimeout(function () {
            WF.api('logout.php', {}).then(function () {
              window.location.href = 'login.html';
            });
          }, 1600);
        } else {
          showAlert(r.message || '修改失败', 'err');
          WF.toast(r.message || '修改失败', 'err');
        }
      });
    });
  }
})();
