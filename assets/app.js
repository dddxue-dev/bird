/* ============================================================
   Wings for Life · 前端公共脚本
   - 与后端 API 通信（前后端分离：前端只负责渲染，逻辑全在 api/ 里）
   - 页头登录态渲染
   - Toast 提示
   - 滚动出现动画
   ============================================================ */

window.WF = (function () {
  'use strict';

  var API_BASE = 'api/';

  /* ---------------- 数据库配置错误提示条 ---------------- */

  function showDbError(message, detail) {
    if (document.getElementById('db-error-banner')) { return; }

    var bar = document.createElement('div');
    bar.id = 'db-error-banner';
    bar.className = 'db-error-banner';

    var h = document.createElement('strong');
    h.textContent = '后端数据库连接失败：' + (message || '');

    var p1 = document.createElement('span');
    p1.textContent = '请检查 api/db.config.php 里的数据库密码是否与 PHPStudy 面板「数据库」中一致，' +
                     '并确认 MySQL 服务已启动。';

    var p2 = document.createElement('span');
    p2.innerHTML = '也可以打开 <a href="setup.php">setup.php</a> 用图形界面测试并自动写入配置。';

    var det = document.createElement('code');
    det.textContent = detail || '';

    bar.appendChild(h);
    bar.appendChild(p1);
    if (detail) { bar.appendChild(det); }
    bar.appendChild(p2);

    if (document.body.firstChild) {
      document.body.insertBefore(bar, document.body.firstChild);
    } else {
      document.body.appendChild(bar);
    }
  }

  /* ---------------- 请求封装 ---------------- */

  function request(file, payload, method) {
    var opt = {
      method: method || 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    };
    if (opt.method !== 'GET' && payload !== undefined && payload !== null) {
      opt.headers['Content-Type'] = 'application/json';
      opt.body = JSON.stringify(payload);
    }
    return fetch(API_BASE + file, opt).then(function (res) {
      return res.json().catch(function () {
        return { ok: false, error: 'bad_response', message: '服务器返回了非 JSON 内容（HTTP ' + res.status + '）' };
      }).then(function (body) {
        body = body || {};
        if (typeof body.http_status === 'undefined') { body.http_status = res.status; }
        if (body.error === 'db_error') {
          showDbError(body.message, body.detail);
        }
        return body;
      });
    }).catch(function (e) {
      return { ok: false, error: 'network', message: '网络请求失败：' + e.message };
    });
  }

  function api(file, data) { return request(file, data || {}, 'POST'); }
  function apiGet(file) { return request(file, null, 'GET'); }

  /* ---------------- Toast ---------------- */

  function toastHost() {
    var h = document.getElementById('toast-host');
    if (!h) {
      h = document.createElement('div');
      h.id = 'toast-host';
      document.body.appendChild(h);
    }
    return h;
  }

  function toast(message, type, ms) {
    var el = document.createElement('div');
    el.className = 'toast ' + (type || 'ok');
    el.textContent = message;
    toastHost().appendChild(el);
    setTimeout(function () {
      el.classList.add('hide');
      setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 300);
    }, ms || 3200);
  }

  /* ---------------- 页头登录态 ---------------- */

  function renderAccount(state) {
    var box = document.getElementById('account-area');
    if (!box) { return; }

    if (state && state.logged_in) {
      var name = String(state.username == null ? '' : state.username);
      box.innerHTML =
        '<a class="btn btn-ghost" href="profile.html">用户中心</a>' +
        '<span class="who" title="当前登录账号"></span>' +
        '<button class="btn btn-line" type="button" id="nav-logout">退出</button>';
      box.querySelector('.who').textContent = name;

      var btn = document.getElementById('nav-logout');
      if (btn) {
        btn.addEventListener('click', function () {
          btn.disabled = true;
          api('logout.php', {}).then(function () {
            window.location.href = 'index.html';
          });
        });
      }
    } else {
      box.innerHTML =
        '<a class="btn btn-ghost" href="login.html">登录</a>' +
        '<a class="btn btn-solid" href="register.html">注册</a>';
    }
  }

  function syncAccount() {
    return apiGet('profile.php').then(function (r) {
      renderAccount(r && r.logged_in ? r : null);
      return r;
    }).catch(function () {
      renderAccount(null);
    });
  }

  /* ---------------- 滚动出现 ---------------- */

  function initReveal() {
    var els = document.querySelectorAll('.reveal');
    if (!els.length) { return; }

    if (!('IntersectionObserver' in window)) {
      for (var i = 0; i < els.length; i++) { els[i].classList.add('in'); }
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('in');
          io.unobserve(en.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    for (var j = 0; j < els.length; j++) { io.observe(els[j]); }
  }

  /* ---------------- 页头阴影 ---------------- */

  function initHeader() {
    var header = document.querySelector('.site-header');
    if (!header) { return; }
    function onScroll() {
      if (window.scrollY > 8) { header.classList.add('scrolled'); }
      else { header.classList.remove('scrolled'); }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------------- 表单按钮 loading ---------------- */

  function withLoading(btn, fn) {
    if (!btn) { return fn(); }
    var html = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '处理中…';
    return Promise.resolve(fn()).then(function (r) {
      btn.disabled = false;
      btn.innerHTML = html;
      return r;
    }, function (e) {
      btn.disabled = false;
      btn.innerHTML = html;
      throw e;
    });
  }

  /* ---------------- 启动 ---------------- */

  document.addEventListener('DOMContentLoaded', function () {
    initHeader();
    initReveal();
    syncAccount();
  });

  return {
    api: api,
    apiGet: apiGet,
    toast: toast,
    syncAccount: syncAccount,
    withLoading: withLoading
  };
})();
