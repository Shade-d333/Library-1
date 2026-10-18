'use strict';

const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

const LOGGED = document.body.dataset.logged === '1';

/* ---------- toast ---------- */
let toastTimer;
function toast(message, ok = true) {
  const el = $('#toast');
  if (!el) return;
  el.textContent = message;
  el.className = 'toast is-visible' + (ok ? '' : ' toast--err');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => (el.className = 'toast'), 2600);
}

/* ---------- API ---------- */
async function api(action, data = {}) {
  const fd = new FormData();
  fd.append('action', action);
  Object.entries(data).forEach(([k, v]) => fd.append(k, v));
  const res = await fetch('api.php', { method: 'POST', body: fd });
  return res.json();
}

/* ---------- модальные окна ---------- */
function openModal(id)  { $(id)?.classList.add('is-open'); }
function closeModal(el) { el.closest('.modal')?.classList.remove('is-open'); }

document.addEventListener('click', (ev) => {
  const closer = ev.target.closest('[data-close]');
  if (closer) closeModal(closer);
});

/* =========================================================
   КАТАЛОГ / ПОИСК — карточка книги
   ========================================================= */
let currentBook = null;

$$('.js-book').forEach((card) => {
  card.addEventListener('click', () => {
    currentBook = {
      id:        card.dataset.id,
      title:     card.dataset.title,
      author:    card.dataset.author,
      genre:     card.dataset.genre,
      isbn:      card.dataset.isbn,
      desc:      card.dataset.desc,
      available: card.dataset.available === '1',
      gradient:  card.dataset.gradient,
    };

    $('#mCover').style.background = currentBook.gradient;
    $('#mTitle').textContent      = currentBook.title;
    $('#mAuthor').textContent     = currentBook.author;
    $('#mGenre').textContent      = currentBook.genre;
    $('#mIsbn').textContent       = 'ISBN: ' + (currentBook.isbn || '—');
    $('#mDesc').textContent       = currentBook.desc || '';

    const status = $('#mStatus');
    status.textContent = currentBook.available ? 'Доступна' : 'Выдана';
    status.className   = 'pill ' + (currentBook.available ? 'pill--violet' : 'pill--green');

    const btn = $('#mTake');
    btn.disabled      = !currentBook.available;
    btn.style.opacity = currentBook.available ? '1' : '.5';
    $('#mHint').textContent = LOGGED
      ? (currentBook.available ? '' : 'Заявку можно подать только на доступную книгу.')
      : 'Для подачи заявки необходимо авторизоваться.';

    openModal('#bookModal');
  });
});

$('#mTake')?.addEventListener('click', async () => {
  if (!LOGGED) { location.href = 'login.php'; return; }
  if (!currentBook?.available) { toast('Книга выдана', false); return; }

  const r = await api('request', { book_id: currentBook.id });
  toast(r.message || r.error, r.ok);
  if (r.ok) setTimeout(() => location.reload(), 900);
});

/* =========================================================
   ЧИТАТЕЛЬ — отмена заявки
   ========================================================= */
$$('.js-cancel-request').forEach((btn) => {
  btn.addEventListener('click', async () => {
    if (!confirm(`Отменить заявку на «${btn.dataset.title}»?`)) return;
    const r = await api('cancel_request', { request_id: btn.dataset.request });
    toast(r.message || r.error, r.ok);
    if (r.ok) setTimeout(() => location.reload(), 700);
  });
});

/* =========================================================
   АДМИН — выдача по заявке (быстрая, без выбора книги)
   ========================================================= */
$$('.js-issue-request').forEach((btn) => {
  btn.addEventListener('click', async () => {
    btn.disabled = true;
    const r = await api('issue', {
      user_id:    btn.dataset.user,
      book_id:    btn.dataset.book,
      request_id: btn.dataset.request,
    });
    toast(r.message || r.error, r.ok);
    if (r.ok) setTimeout(() => location.reload(), 900);
    else      btn.disabled = false;
  });
});

/* =========================================================
   АДМИН — отклонение заявки
   ========================================================= */
let rejectReqId = 0;

$$('.js-reject-request').forEach((btn) => {
  btn.addEventListener('click', () => {
    rejectReqId = btn.dataset.request;
    $('#rejReader').textContent = btn.dataset.reader;
    $('#rejTitle').textContent  = btn.dataset.title;
    openModal('#rejectModal');
  });
});

$('#rejConfirm')?.addEventListener('click', async () => {
  const r = await api('reject_request', { request_id: rejectReqId });
  toast(r.message || r.error, r.ok);
  if (r.ok) setTimeout(() => location.reload(), 700);
});

/* =========================================================
   АДМИН — выдача книги через модалку (без заявки)
   ========================================================= */
let issueUser = 0;

$$('.js-issue').forEach((btn) => {
  btn.addEventListener('click', () => {
    issueUser = btn.dataset.user;
    const readerName = btn.closest('.reader')
      ?.querySelector('.reader__info b')?.textContent || '';
    $('#issueUser').textContent = readerName;
    openModal('#issueModal');
  });
});

$$('.js-pick-book').forEach((item) => {
  item.addEventListener('click', async () => {
    const r = await api('issue', { user_id: issueUser, book_id: item.dataset.id });
    toast(r.message || r.error, r.ok);
    if (r.ok) setTimeout(() => location.reload(), 900);
  });
});

/* =========================================================
   АДМИН — продление срока
   ========================================================= */
let extendLoan = 0;

$$('.js-extend').forEach((btn) => {
  btn.addEventListener('click', () => {
    extendLoan = btn.dataset.loan;
    $('#extUser').textContent  = btn.dataset.user;
    $('#extTitle').textContent = btn.dataset.title;
    openModal('#extendModal');
  });
});

$('#extConfirm')?.addEventListener('click', async () => {
  const r = await api('extend', { loan_id: extendLoan, days: $('#extDays').value });
  toast(r.message || r.error, r.ok);
  if (r.ok) setTimeout(() => location.reload(), 900);
});

/* =========================================================
   АДМИН — приём книги
   ========================================================= */
let acceptLoan = 0;

$$('.js-accept').forEach((btn) => {
  btn.addEventListener('click', () => {
    acceptLoan = btn.dataset.loan;
    $('#accUser').textContent  = btn.dataset.user;
    $('#accTitle').textContent = btn.dataset.title;
    openModal('#acceptModal');
  });
});

$('#accConfirm')?.addEventListener('click', async () => {
  const r = await api('accept', { loan_id: acceptLoan });
  toast(r.message || r.error, r.ok);
  if (r.ok) setTimeout(() => location.reload(), 900);
});
/* =========================================================
   ЧИТАТЕЛЬ — возврат книги
   ========================================================= */
let returnLoan = 0;

$$('.js-return').forEach((btn) => {
  btn.addEventListener('click', () => {
    returnLoan = btn.dataset.loan;
    $('#retTitle').textContent = btn.dataset.title;
    openModal('#returnModal');
  });
});

$('#retConfirm')?.addEventListener('click', async () => {
  const r = await api('request_return', { loan_id: returnLoan });
  toast(r.message || r.error, r.ok);
  if (r.ok) setTimeout(() => location.reload(), 900);
});