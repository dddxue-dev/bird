/* ============================================================
   登录 / 注册页脚本
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

  /* ---------------- 登录 ---------------- */

  var loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', function (e) {
      e.preventDefault();
      hideAlert();

      var username = loginForm.username.value;
      var password = loginForm.password.value;

      if (!username || !password) {
        showAlert('请输入用户名和密码', 'err');
        return;
      }

      WF.withLoading(loginForm.querySelector('button[type=submit]'), function () {
        return WF.api('login.php', { username: username, password: password });
      }).then(function (r) {
        if (r.ok) {
          WF.toast('登录成功，正在进入用户中心…', 'ok');
          setTimeout(function () { window.location.href = 'profile.html'; }, 600);
        } else {
          showAlert(r.message || '登录失败', 'err');
        }
      });
    });

    // 已登录则直接进用户中心
    WF.apiGet('profile.php').then(function (r) {
      if (r && r.logged_in) { window.location.href = 'profile.html'; }
    });
  }

  /* ---------------- 注册 ---------------- */

  var regForm = document.getElementById('register-form');
  if (regForm) {
    regForm.addEventListener('submit', function (e) {
      e.preventDefault();
      hideAlert();

      var username = regForm.username.value;
      var password = regForm.password.value;
      var repass = regForm.re_password.value;

      if (!username || !password) {
        showAlert('用户名和密码都不能为空', 'err');
        return;
      }
      if (password !== repass) {
        showAlert('两次输入的密码不一致', 'err');
        return;
      }

      WF.withLoading(regForm.querySelector('button[type=submit]'), function () {
        return WF.api('register.php', {
          username: username, password: password, re_password: repass
        });
      }).then(function (r) {
        if (r.ok) {
          showAlert('注册成功，正在跳转到登录页…', 'ok');
          WF.toast('账号创建成功', 'ok');
          setTimeout(function () { window.location.href = 'login.html'; }, 900);
        } else {
          showAlert(r.message || '注册失败', 'err');
        }
      });
    });
  }
})();
