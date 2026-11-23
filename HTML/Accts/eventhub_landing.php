<?php
// ==============================================================
// FILE: eventhub_landing.php
// Home page + single-page customer flow (browse -> details -> seats -> checkout -> confirmation -> my tickets).
// PHP at the top supplies the signed-in user; the rest is HTML, CSS and JavaScript.
// Demo only: events and orders live in the browser (localStorage), not the database.
// Search tags used below: SECTION:, PHP:, CSS:, HTML:, HOME:, VIEW:, JS:, FUNC
// ==============================================================
require_once __DIR__ . '/config.php';

// --- PHP: signed-in user (null for guests) and the letter shown on the avatar button ---
$user = current_user();
$initial = $user ? strtoupper(mb_substr($user['Username'], 0, 1)) : '';
?>
<!DOCTYPE html>
<!-- ==============================================================
     HTML: PAGE
     Head (meta, fonts, styles), then header, main views, footer, script
     ============================================================== -->
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#ffffff">
  <title>EventHub — Events and Tickets</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Gloock&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>

    /* ==============================================================
       CSS: DESIGN TOKENS
       Colours, shadow and fonts used everywhere
       ============================================================== */
    :root {
      --ink:#171615; --muted:#77736f; --line:#e7e4e1; --paper:#fff;
      --soft:#f7f6f5; --coral:#ed626b; --orange:#f36b32; --shadow:0 10px 28px rgba(31,24,20,.08);
    }

    /* --- CSS: BASE / RESET --- */
    * { box-sizing:border-box; }
    html { scroll-behavior:smooth; }
    body { margin:0; color:var(--ink); background:var(--paper); font-family:Inter,Arial,sans-serif; }
    button,input,select { font:inherit; }
    button,a { -webkit-tap-highlight-color:transparent; }
    button { cursor:pointer; }

    /* --- CSS: PAGE WIDTH HELPER (.wrap centres content) --- */
    .wrap { width:min(1180px, calc(100% - 64px)); margin:0 auto; }

    /* ==============================================================
       CSS: HEADER & NAVIGATION
       Logo, nav links, pill buttons, mobile menu toggle
       ============================================================== */
    .topbar { height:68px; display:flex; align-items:center; justify-content:space-between; gap:24px; }
    .brand { font-family:Gloock,Georgia,serif; font-size:17px; letter-spacing:-.4px; text-decoration:none; color:var(--ink); white-space:nowrap; }
    nav { display:flex; align-items:center; justify-content:center; gap:30px; }
    nav a { color:#4d4946; font-size:12px; text-decoration:none; transition:color .2s; }
    nav a:hover { color:var(--coral); }
    .nav-actions { display:flex; align-items:center; gap:10px; }
    .pill { border:1px solid var(--ink); border-radius:99px; padding:10px 17px; font-size:11px; font-weight:600; background:#fff; color:var(--ink); }
    .pill.dark { background:#111; color:white; }
    .pill { text-decoration:none; display:inline-block; }
    .pill:hover { transform:translateY(-1px); }
    .menu-toggle { display:none; border:0; background:none; font-size:22px; }

    /* --- CSS: HOME - HERO — Heading and intro text --- */
    .hero { text-align:center; padding:55px 0 42px; }
    .eyebrow { display:inline-block; border:1px solid #efedeb; border-radius:99px; color:#8a8580; font-size:9px; letter-spacing:1.2px; padding:8px 15px; text-transform:uppercase; }
    h1,h2,h3,p { margin-top:0; }
    h1 { font-family:Gloock,Georgia,serif; font-weight:400; font-size:clamp(42px,6.2vw,76px); letter-spacing:-2.5px; line-height:1.04; margin:22px 0 13px; }
    .hero-copy { color:var(--muted); font-size:13px; margin:0 auto 27px; max-width:620px; line-height:1.7; }

    /* --- CSS: HOME - HERO SEARCH BAR --- */
    .searchbar { width:min(820px,100%); margin:0 auto 34px; display:grid; grid-template-columns:1.2fr 1fr 1fr auto; gap:4px; padding:6px; border-radius:99px; background:#f7f6f6; box-shadow:0 7px 22px rgba(0,0,0,.055); text-align:left; }
    .search-field { min-width:0; display:flex; flex-direction:column; justify-content:center; padding:7px 17px; border-right:1px solid #e8e5e3; }
    .search-field label { color:#8d8884; font-size:8px; text-transform:uppercase; letter-spacing:.7px; margin-bottom:4px; }
    .search-field input,.search-field select { border:0; outline:0; background:transparent; color:#292624; font-size:11px; width:100%; min-width:0; }
    .search-field input::placeholder { color:#55504d; opacity:1; }
    .search-button { align-self:center; border:0; border-radius:99px; background:var(--coral); color:white; padding:14px 27px; font-size:11px; font-weight:700; min-width:96px; transition:background .2s,transform .2s; }
    .search-button:hover { background:#dc505a; transform:translateY(-1px); }

    /* --- CSS: DECORATIVE STRIPE ARTWORK (hero + organiser section) --- */
    .stripe-art { position:relative; overflow:hidden; border-radius:16px; background:
      repeating-linear-gradient(90deg,rgba(132,75,56,.17) 0 3px,rgba(255,231,212,.38) 6px 10px,rgba(255,247,233,.5) 13px 17px,rgba(176,111,84,.15) 20px 24px),
      linear-gradient(105deg,#d89c80,#f4c7a9 48%,#dc9c80); }
    .stripe-art:after { content:""; position:absolute; inset:0; background:repeating-linear-gradient(90deg,transparent 0 12px,rgba(255,255,255,.2) 14px 17px,transparent 21px 29px); filter:blur(3px); }
    .hero-art { height:clamp(150px,22vw,275px); width:100%; }

    /* --- CSS: SECTION HEADINGS --- */
    .section { padding:54px 0 58px; border-top:1px solid #f0eeec; }
    .section-head { display:flex; justify-content:space-between; align-items:end; gap:20px; margin-bottom:23px; }
    .kicker { color:#aaa39d; font-size:9px; text-transform:uppercase; letter-spacing:1.1px; margin-bottom:10px; }
    h2 { font-family:Gloock,Georgia,serif; font-size:clamp(30px,4vw,43px); font-weight:400; letter-spacing:-.8px; margin:0; }
    .text-link { border:1px solid var(--line); border-radius:99px; background:white; padding:9px 17px; color:#37322f; font-size:10px; text-decoration:none; white-space:nowrap; }
    .text-link:hover { border-color:#aaa; }

    /* --- CSS: HOME - EVENT CARDS ("Happening soon") --- */
    .event-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:22px; }
    .event-card { min-width:0; border-radius:13px; padding:8px 8px 14px; box-shadow:var(--shadow); background:#fff; transition:transform .2s,box-shadow .2s; }
    .event-card:hover { transform:translateY(-4px); box-shadow:0 15px 34px rgba(31,24,20,.12); }
    .event-art { height:clamp(145px,19vw,245px); border-radius:10px; position:relative; overflow:hidden; }
    .event-art.stage { background:radial-gradient(ellipse at 50% 0%,rgba(255,145,65,.45),transparent 48%),repeating-linear-gradient(90deg,#351e35 0 4px,#713b45 7px,#d66a31 13px,#3d2444 19px); }
    .event-art.cup { background:repeating-linear-gradient(90deg,rgba(142,80,57,.15) 0 4px,rgba(255,224,202,.5) 8px 13px,rgba(255,244,227,.55) 17px 22px),linear-gradient(120deg,#d9a087,#f4c5a8); }
    .event-art.comedy { background:radial-gradient(ellipse at 45% 35%,#fa9acb 0,#e65ca7 55%,#c83c91 100%); }
    .category { position:absolute; top:12px; left:12px; border-radius:99px; padding:6px 10px; background:rgba(255,255,255,.92); color:#35302d; font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
    .event-info { padding:13px 8px 0; }
    .event-date { color:#9a938d; font-size:9px; text-transform:uppercase; letter-spacing:.6px; margin-bottom:7px; }
    .event-title { font-family:Gloock,Georgia,serif; font-size:20px; font-weight:400; margin-bottom:9px; }
    .event-bottom { display:flex; justify-content:space-between; align-items:center; gap:10px; }
    .price { color:#8b8580; font-size:10px; }
    .ticket-btn { background:#111; color:#fff; border:0; border-radius:99px; padding:9px 14px; font-size:9px; font-weight:700; white-space:nowrap; }
    .ticket-btn:hover { background:var(--coral); }
    .empty-state { display:none; padding:34px; text-align:center; color:var(--muted); border:1px dashed var(--line); border-radius:12px; }

    /* --- CSS: HOME - "FOUR STEPS" SECTION --- */
    .steps { padding:30px 0 65px; }
    .steps h2 { margin-bottom:26px; }
    .step-grid { display:grid; grid-template-columns:repeat(4,1fr); border-top:1px solid #dcd8d4; padding-top:20px; gap:22px; }
    .step-num { color:#aaa39d; font-size:9px; letter-spacing:1px; margin-bottom:18px; }
    .step-title { font-family:Gloock,Georgia,serif; font-size:23px; margin-bottom:8px; }
    .step-copy { color:#85807b; font-size:10px; line-height:1.7; max-width:210px; }

    /* --- CSS: HOME - ORGANISER CALL TO ACTION --- */
    .organiser { border-top:1px solid #dedad6; padding:50px 0 70px; display:grid; grid-template-columns:.9fr 1.1fr; gap:45px; align-items:center; }
    .organiser h2 { font-size:clamp(38px,5vw,56px); line-height:1.03; margin-bottom:17px; }
    .organiser p { color:var(--muted); font-size:12px; line-height:1.7; max-width:330px; margin-bottom:20px; }
    .orange-btn { border:0; border-radius:2px; background:var(--orange); color:white; padding:12px 18px; font-size:10px; font-weight:700; }
    .orange-btn:hover { background:#df5723; }
    .organiser-art { height:clamp(220px,30vw,350px); border-radius:0; }

    /* --- CSS: FOOTER --- */
    footer { border-top:1px solid #eeeae7; padding:35px 0 18px; }
    .footer-grid { display:grid; grid-template-columns:1.5fr repeat(3,1fr); gap:35px; padding-bottom:35px; }
    .footer-brand p { color:#8d8781; font-size:10px; line-height:1.7; max-width:190px; margin-top:15px; }
    .footer-col h4 { color:#aaa39d; font-size:9px; letter-spacing:1px; text-transform:uppercase; margin:3px 0 14px; }
    .footer-col a { display:block; color:#4d4844; text-decoration:none; font-size:10px; margin:0 0 10px; }
    .footer-col a:hover { color:var(--coral); }
    .copyright { border-top:1px solid #eeeae7; padding-top:16px; display:flex; justify-content:space-between; color:#a39d97; font-size:9px; }

    /* --- CSS: MODAL / QUANTITY / NOTICE STYLES — Not used by the current markup (left over for future pop-ups) --- */
    .modal-backdrop { display:none; position:fixed; inset:0; background:rgba(18,15,13,.45); z-index:20; align-items:center; justify-content:center; padding:20px; }
    .modal-backdrop.open { display:flex; }
    .modal { width:min(440px,100%); background:white; border-radius:16px; padding:28px; box-shadow:0 24px 80px rgba(0,0,0,.2); position:relative; }
    .modal-close { position:absolute; top:13px; right:15px; border:0; background:none; font-size:23px; color:#777; }
    .modal h3 { font:400 29px Gloock,Georgia,serif; margin:0 28px 8px 0; }
    .modal p { color:var(--muted); font-size:11px; line-height:1.6; margin-bottom:20px; }
    .modal label { display:block; font-size:10px; font-weight:600; margin:13px 0 6px; }
    .modal input,.modal select { width:100%; border:1px solid var(--line); border-radius:7px; padding:11px; font-size:12px; outline:none; }
    .modal input:focus,.modal select:focus { border-color:#888; }
    .modal .submit { width:100%; margin-top:18px; border:0; border-radius:99px; background:#111; color:white; padding:13px; font-size:11px; font-weight:700; }
    .modal .submit:hover { background:var(--coral); }
    .modal-note { font-size:9px!important; margin:12px 0 0!important; color:#9a938d!important; }
    .qty-row { display:flex; justify-content:space-between; align-items:center; margin:15px 0; font-size:11px; }
    .qty-control { display:flex; align-items:center; gap:13px; }
    .qty-control button { width:27px; height:27px; border:1px solid var(--line); background:#fff; border-radius:50%; }
    .notice { display:none; padding:10px 12px; margin-top:12px; background:#f4f2ef; border-radius:7px; color:#57514c; font-size:10px; line-height:1.5; }
    .notice.show { display:block; }

    /* --- CSS: RESPONSIVE (home page) - tablet / phone --- */
    @media(max-width:760px) {
      .wrap { width:min(100% - 36px,600px); }
      .topbar { height:62px; }
      nav { display:none; position:absolute; top:62px; left:0; right:0; z-index:5; padding:18px 24px; background:#fff; box-shadow:var(--shadow); flex-direction:column; gap:18px; }
      nav.open { display:flex; }
      .menu-toggle { display:block; }
      .nav-actions .pill { padding:8px 11px; font-size:9px; }
      .hero { padding:45px 0 35px; }
      h1 { letter-spacing:-1.5px; }
      .searchbar { border-radius:18px; grid-template-columns:1fr 1fr; padding:10px; }
      .search-field { padding:9px 10px; }
      .search-field:nth-child(2) { border-right:0; }
      .search-field:nth-child(3) { border-top:1px solid #e8e5e3; }
      .search-button { width:100%; }
      .event-grid { gap:12px; }
      .event-art { height:150px; }
      .event-title { font-size:17px; }
      .ticket-btn { padding:8px 10px; }
      .organiser { grid-template-columns:1fr; gap:24px; }
      .organiser-art { grid-row:1; height:230px; }
      .footer-grid { grid-template-columns:repeat(2,1fr); }
    }

    /* --- CSS: RESPONSIVE (home page) - small phones --- */
    @media(max-width:500px) {
      .nav-actions { gap:6px; }
      .nav-actions .pill { padding:8px 9px; }
      .hero { padding-top:34px; }
      h1 { font-size:43px; }
      .hero-copy { font-size:11px; }
      .searchbar { grid-template-columns:1fr 1fr; }
      .search-field:first-child { grid-column:1/-1; border-right:0; border-bottom:1px solid #e8e5e3; }
      .event-grid { grid-template-columns:1fr; }
      .event-card { display:grid; grid-template-columns:42% 1fr; gap:7px; align-items:center; }
      .event-art { height:145px; }
      .event-info { padding:8px; }
      .event-title { font-size:20px; }
      .step-grid { grid-template-columns:repeat(2,1fr); row-gap:25px; }
      .steps { padding-bottom:45px; }
      .section { padding:42px 0; }
      .footer-grid { gap:25px 15px; }
      .copyright { gap:10px; flex-wrap:wrap; }
    }

    /* --- CSS: UTILITY - elements with the hidden attribute are always hidden --- */
    [hidden] { display:none !important; }

/* ==============================================================
   CSS: ACCOUNT AREA
   Guest buttons, profile avatar button and dropdown menu
   ============================================================== */
#guestActions { display:flex; align-items:center; gap:10px; }
.profile-menu { position:relative; }
.profile-btn {
  width:38px; height:38px; border-radius:50%; border:1px solid var(--ink);
  background:#fff; color:var(--ink); font-size:14px; font-weight:600;
  display:grid; place-items:center; padding:0; overflow:hidden;
}
.profile-btn img { width:100%; height:100%; object-fit:cover; }
.profile-btn:hover { border-color:var(--coral); }
.profile-btn:focus-visible { outline:2px solid var(--ink); outline-offset:2px; }
.profile-dropdown {
  position:absolute; right:0; top:46px; min-width:160px; z-index:15;
  background:#fff; border:1px solid var(--line); border-radius:12px;
  box-shadow:var(--shadow); padding:6px;
}
.profile-dropdown a, .profile-dropdown button {
  display:block; width:100%; text-align:left; border:0; background:none;
  padding:10px 12px; border-radius:8px; font-size:12px; color:var(--ink); text-decoration:none;
}
.profile-dropdown a:hover, .profile-dropdown button:hover { background:var(--soft); color:var(--coral); }

    /* ==============================================================
       CSS: CUSTOMER TICKETING FLOW
       Styles for the Browse, Details, Seats, Checkout, Confirmation and My Tickets views
       ============================================================== */
    .workflow-page { padding:42px 0 70px; }
    .workflow-page[hidden] { display:none !important; }
    .workflow-page .page-top { margin-bottom:28px; }
    .workflow-page .page-top h1 { font-size:clamp(38px,5vw,58px); margin:16px 0 10px; }
    .workflow-page .page-top p { color:var(--muted); font-size:12px; line-height:1.7; }
    .workflow-back { border:0; background:none; color:#716b66; padding:0; margin:0 0 25px; font-size:11px; }
    .workflow-back:hover { color:var(--coral); }

    /* --- CSS: BROWSE VIEW - filter bar --- */
    .filter-panel { display:grid; grid-template-columns:1.1fr 1fr 1fr auto; gap:12px; align-items:end; padding:16px; border-radius:14px; background:var(--soft); margin-bottom:24px; }
    .filter-panel label,.checkout-form label { display:flex; flex-direction:column; gap:7px; color:#77716c; font-size:10px; }
    .filter-panel input,.filter-panel select,.checkout-form input { width:100%; border:1px solid var(--line); border-radius:8px; background:white; padding:12px; color:var(--ink); font-size:11px; outline:none; }
    .filter-panel input:focus,.filter-panel select:focus,.checkout-form input:focus { border-color:var(--coral); }
    .workflow-count { display:flex; justify-content:space-between; color:#8a837d; font-size:10px; margin:0 0 15px; }

    /* --- CSS: BROWSE VIEW - results grid and cards --- */
    .workflow-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:20px; }
    .workflow-card { border:1px solid #f0edeb; border-radius:13px; padding:8px 8px 15px; background:#fff; box-shadow:var(--shadow); }
    .workflow-card .event-art { height:190px; }
    .workflow-card .event-info { padding:13px 8px 0; }
    .workflow-card .event-title { font-size:21px; }
    .workflow-card .event-bottom { margin-top:12px; }

    /* --- CSS: DETAILS VIEW --- */
    .details-layout,.seat-layout,.checkout-layout { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(260px,.8fr); gap:28px; align-items:start; }
    .details-art { min-height:300px; border-radius:14px; margin-bottom:24px; }
    .details-copy { color:var(--muted); font-size:12px; line-height:1.8; }
    .details-meta { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin:22px 0; }
    .meta-box { border:1px solid var(--line); border-radius:10px; padding:14px; }
    .meta-box small { display:block; color:#99918b; font-size:9px; text-transform:uppercase; letter-spacing:.7px; margin-bottom:7px; }
    .meta-box strong { font-size:12px; font-weight:600; }
    .panel-card { border:1px solid var(--line); border-radius:13px; padding:22px; background:white; box-shadow:var(--shadow); }
    .panel-card h3 { font:400 25px Gloock,Georgia,serif; margin:0 0 14px; }
    .panel-card p { color:var(--muted); font-size:11px; line-height:1.7; }
    .panel-price { font:400 28px Gloock,Georgia,serif; margin:15px 0; }
    .full-button { width:100%; }

    /* --- CSS: SEATS VIEW - stage bar, seat map, legend --- */
    .seat-stage { width:75%; margin:0 auto 26px; padding:12px; text-align:center; border-radius:5px 5px 18px 18px; background:#f4e7df; color:#81756d; font-size:9px; font-weight:700; letter-spacing:2px; }
    .seat-map { display:grid; grid-template-columns:repeat(8,minmax(25px,1fr)); gap:9px; padding:20px 10px; }
    .seat { border:0; border-radius:6px 6px 3px 3px; min-height:32px; background:#e7e2dd; color:#514b46; font-size:9px; }
    .seat:hover:not(:disabled) { outline:2px solid #c9b5a8; }
    .seat.selected { background:var(--coral); color:white; }
    .seat.occupied { background:#292624; color:white; opacity:.35; cursor:not-allowed; }
    .seat-legend { display:flex; justify-content:center; gap:18px; flex-wrap:wrap; color:#817a74; font-size:9px; }
    .seat-legend span { display:flex; align-items:center; gap:6px; }
    .seat-dot { width:10px; height:10px; display:inline-block; border-radius:3px; background:#e7e2dd; }
    .seat-dot.selected { background:var(--coral); }
    .seat-dot.occupied { background:#292624; opacity:.45; }
    .selected-list { color:#77716c; font-size:11px; line-height:1.9; min-height:35px; }

    /* --- CSS: ORDER SUMMARY (checkout and confirmation) --- */
    .order-line { display:flex; justify-content:space-between; gap:12px; padding:12px 0; border-bottom:1px solid var(--line); color:#77716c; font-size:11px; }
    .order-total { display:flex; justify-content:space-between; align-items:center; padding:17px 0; margin-top:4px; border-top:1px solid var(--line); font-size:12px; }
    .order-total strong { font:400 24px Gloock,Georgia,serif; }

    /* --- CSS: CHECKOUT FORM --- */
    .checkout-form { display:grid; gap:17px; border:1px solid var(--line); border-radius:13px; padding:24px; box-shadow:var(--shadow); }
    .checkout-form h2 { font-size:29px; margin:0 0 5px; }
    .checkout-form .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .checkout-form .check-row { display:flex; flex-direction:row; align-items:center; gap:9px; }
    .checkout-form .check-row input { width:auto; }
    .fine-print { color:#9a938d; font-size:9px; line-height:1.6; margin:0; }

    /* --- CSS: CONFIRMATION VIEW --- */
    .confirmation-card { max-width:650px; margin:10px auto 0; text-align:center; border:1px solid var(--line); border-radius:16px; padding:38px; box-shadow:var(--shadow); }
    .success-mark { width:48px; height:48px; margin:0 auto 18px; display:grid; place-items:center; border-radius:50%; background:#edf5ed; color:#477b4d; font-size:24px; }
    .confirmation-card h1 { font-size:clamp(40px,5vw,58px); }
    .confirmation-card > p:not(.eyebrow) { color:var(--muted); font-size:12px; line-height:1.7; }
    .confirmation-summary { text-align:left; margin:24px 0; padding:18px; border-radius:10px; background:var(--soft); }

    /* --- CSS: MY TICKETS VIEW --- */
    .ticket-list { display:grid; gap:15px; }
    .ticket-item { display:grid; grid-template-columns:150px 1fr auto; gap:18px; align-items:center; border:1px solid var(--line); border-radius:13px; padding:10px; box-shadow:var(--shadow); }
    .ticket-item .event-art { height:115px; }
    .ticket-item h3 { font:400 22px Gloock,Georgia,serif; margin:7px 0; }
    .ticket-item p { color:#8a837d; font-size:10px; margin:4px 0; }
    .ticket-code { color:#77716c; font-size:9px; text-align:right; }
    .empty-tickets { border:1px dashed var(--line); border-radius:12px; padding:35px; text-align:center; color:var(--muted); font-size:12px; }
    .inline-action { border:0; background:none; padding:0; color:var(--coral); font-size:11px; text-decoration:underline; }

    /* --- CSS: RESPONSIVE (ticketing views) - tablet / phone --- */
    @media(max-width:760px) {
      .filter-panel { grid-template-columns:1fr 1fr; }
      .workflow-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
      .workflow-card .event-art { height:150px; }
      .details-layout,.seat-layout,.checkout-layout { grid-template-columns:1fr; }
      .ticket-item { grid-template-columns:100px 1fr; }
      .ticket-code { grid-column:2; text-align:left; }
    }

    /* --- CSS: RESPONSIVE (ticketing views) - small phones --- */
    @media(max-width:500px) {
      .workflow-grid { grid-template-columns:1fr; }
      .workflow-card { display:grid; grid-template-columns:40% 1fr; gap:8px; align-items:center; }
      .workflow-card .event-art { height:145px; }
      .workflow-card .event-info { padding:7px; }
      .filter-panel { grid-template-columns:1fr; }
      .details-meta { grid-template-columns:1fr 1fr; }
      .seat-map { gap:5px; padding:15px 0; }
      .seat { min-height:29px; font-size:8px; }
      .checkout-form .form-row { grid-template-columns:1fr; }
      .confirmation-card { padding:25px 17px; }
    }

  </style>
</head>
<body>

  <!-- ==============================================================
       HTML: HEADER
       Logo, main navigation and account area
       ============================================================== -->
  <header class="wrap topbar">
    <a class="brand" href="eventhub_landing.php" aria-label="EventHub home">EventHub</a>
    <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation" aria-expanded="false">☰</button>

    <!-- --- HTML: main navigation (data-filter links filter the Browse view) --- -->
    <nav id="mainNav" aria-label="Main navigation">
      <a href="#events" data-filter="All">Discover</a>
      <a href="#events" data-filter="Concert">Concerts</a>
      <a href="#events" data-filter="Sport">Sport</a>
      <a href="#events" data-filter="Theatre">Theatre</a>
      <a href="#organisers">Experiences</a>
      <a href="#tickets" data-open-page="tickets">My tickets</a>
    </nav>

    <!-- --- HTML: account area - guests see Create Account / Sign in; signed-in users see an avatar menu --- -->
    <div class="nav-actions">
  <button class="pill" type="button" data-go="tickets">My tickets</button>

<?php /* --- PHP: GUEST view --- */ ?>
<?php if (!$user): ?>
  <div id="guestActions">
    <a class="pill dark" id="SignUpBtn" href="eventhub_signup.php">Create Account</a>
    <a class="pill" id="LoginBtn" href="eventhub_signIn.php">Sign in</a>
  </div>

<?php /* --- PHP: SIGNED-IN view (avatar, dropdown with My profile and Sign out) --- */ ?>
<?php else: ?>
  <div class="profile-menu" id="profileMenu">
    <button class="profile-btn" id="profileBtn" aria-label="Account menu" aria-haspopup="true" aria-expanded="false">
      <?php if (!empty($user['ProfileImg'])): ?>
        <img src="<?= e($user['ProfileImg']) ?>" alt="">
      <?php else: ?>
        <span id="profileInitial"><?= e($initial) ?></span>
      <?php endif; ?>
    </button>
    <div class="profile-dropdown" id="profileDropdown" hidden>
      <a href="eventhub_profile.php">My profile</a>

      <?php /* --- PHP: sign-out is a POST form so it can carry a CSRF token --- */ ?>
      <form method="post" action="logout.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button type="submit" id="signOutBtn">Sign out</button>
      </form>
    </div>
  </div>
<?php endif; ?>
</div>
  </header>

  <!-- ==============================================================
       HTML: MAIN CONTENT
       Home sections first, then the hidden customer views that JavaScript swaps in
       ============================================================== -->
  <main>

    <!-- ==============================================================
         HOME: HERO + SEARCH
         Search sends the visitor to the Browse view with their filters applied
         ============================================================== -->
    <section class="wrap hero">
      <span class="eyebrow">Your city, your next experience</span>
      <h1>Find your next night out</h1>
      <p class="hero-copy">Concerts, sport, theatre and conferences. One place to browse, book and keep your tickets.</p>

      <form class="searchbar" id="eventSearch">
        <div class="search-field">
          <label for="keyword">Find</label>
          <input id="keyword" type="search" placeholder="Key word" aria-label="Search events by keyword">
        </div>
        <div class="search-field">
          <label for="location">Location</label>
          <input id="location" type="text" value="Kamloops, BC" aria-label="Event location">
        </div>
        <div class="search-field">
          <label for="when">When</label>
          <select id="when" aria-label="When">
            <option value="any">This month</option>
            <option value="weekend">This weekend</option>
            <option value="upcoming">Upcoming</option>
          </select>
        </div>
        <button class="search-button" type="submit">Search</button>
      </form>
      <div class="stripe-art hero-art" role="img" aria-label="Peach and cream vertical striped artwork"></div>
    </section>

    <!-- ==============================================================
         HOME: HAPPENING SOON
         Static event cards; data-category / data-search power the live filter on this page
         ============================================================== -->
    <section class="section" id="events">
      <div class="wrap">
        <div class="section-head">
          <div><div class="kicker">The good stuff</div><h2>Happening soon</h2></div>
          <a class="text-link" href="#events" id="viewAll">View all&nbsp; ↗</a>
        </div>
        <div class="event-grid" id="eventGrid">
          <article class="event-card" data-category="Concert" data-search="summer stage live music concert outdoor">
            <div class="event-art stage"><span class="category">Concert</span></div>
            <div class="event-info">
              <div class="event-date">14 Jun · 7:00 PM</div>
              <h3 class="event-title">Summer Stage</h3>
              <div class="event-bottom"><span class="price">From $35</span><button class="ticket-btn" data-event="Summer Stage" data-price="35">Get tickets</button></div>
            </div>
          </article>
          <article class="event-card" data-category="Sport" data-search="cup final football soccer sport championship">
            <div class="event-art cup"><span class="category">Sport</span></div>
            <div class="event-info">
              <div class="event-date">21 Jun · 6:30 PM</div>
              <h3 class="event-title">Cup Final</h3>
              <div class="event-bottom"><span class="price">From $28</span><button class="ticket-btn" data-event="Cup Final" data-price="28">Get tickets</button></div>
            </div>
          </article>
          <article class="event-card" data-category="Theatre" data-search="open mic club comedy theatre standup">
            <div class="event-art comedy"><span class="category">Theatre</span></div>
            <div class="event-info">
              <div class="event-date">28 Jun · 8:00 PM</div>
              <h3 class="event-title">Open Mic Club</h3>
              <div class="event-bottom"><span class="price">From $15</span><button class="ticket-btn" data-event="Open Mic Club" data-price="15">Get tickets</button></div>
            </div>
          </article>
        </div>
        <div class="empty-state" id="emptyState">No events match those filters. Try another keyword or category.</div>
      </div>
    </section>

    <!-- ==============================================================
         HOME: FOUR STEPS
         ============================================================== -->
    <section class="wrap steps">
      <div class="kicker">Easy as it should be</div>
      <h2>Four steps, no friction</h2>
      <div class="step-grid">
        <div><div class="step-num">01</div><div class="step-title">Find</div><p class="step-copy">Browse by city, date, or type.</p></div>
        <div><div class="step-num">02</div><div class="step-title">Choose</div><p class="step-copy">Pick seats in a few taps or a quick scroll.</p></div>
        <div><div class="step-num">03</div><div class="step-title">Pay</div><p class="step-copy">One checkout. Keep your receipt and tickets.</p></div>
        <div><div class="step-num">04</div><div class="step-title">Go</div><p class="step-copy">Your tickets are in your wallet, ready to scan.</p></div>
      </div>
    </section>

    <!-- ==============================================================
         HOME: ORGANISER CALL TO ACTION
         "Start selling" goes to the profile (signed in) or sign-up (guest)
         ============================================================== -->
    <section class="wrap organiser" id="organisers">
      <div>
        <div class="kicker">For organisers</div>
        <h2>Sell out,<br>on your terms.</h2>
        <p>Custom ticket types, live sales and payouts in one dashboard.</p>
        <button class="orange-btn" id="startSelling">Start selling</button>
      </div>
      <div class="stripe-art organiser-art" role="img" aria-label="Peach and cream vertical striped artwork"></div>
    </section>

    <!-- ==============================================================
         VIEW: BROWSE
         Filter bar + results (cards injected by renderBrowse())
         ============================================================== -->
    <section class="wrap workflow-page" id="browsePage" hidden>
      <button class="workflow-back" type="button" data-go="home">← Back to EventHub</button>
      <div class="page-top"><span class="eyebrow">THE GOOD STUFF</span><h1>Discover events<span style="color:var(--coral)">.</span></h1><p>Find concerts, sport, theatre and experiences happening around you.</p></div>
      <div class="filter-panel">
        <label>Keyword<input id="browseKeyword" type="search" placeholder="Search event name"></label>
        <label>Category<select id="browseCategory"><option value="all">Any category</option><option value="Concert">Concert</option><option value="Sport">Sport</option><option value="Theatre">Theatre</option></select></label>
        <label>Location<input id="browseLocation" type="text" value="Kamloops, BC" placeholder="Any location"></label>
        <button class="search-button" id="browseSearch" type="button">Search</button>
      </div>
      <div class="workflow-count"><span id="browseCount">All events</span><span>Sample events · Sorted by date</span></div>
      <div class="workflow-grid" id="browseGrid"></div>
      <div class="empty-state" id="browseEmpty">No events match those filters.</div>
    </section>

    <!-- ==============================================================
         VIEW: EVENT DETAILS
         Content injected by openDetails()
         ============================================================== -->
    <section class="wrap workflow-page" id="detailsPage" hidden>
      <button class="workflow-back" type="button" data-go="browse">← Back to events</button>
      <div id="detailsContent"></div>
    </section>

    <!-- ==============================================================
         VIEW: CHOOSE SEATS
         Seat buttons injected by renderSeats()
         ============================================================== -->
    <section class="wrap workflow-page" id="seatsPage" hidden>
      <button class="workflow-back" type="button" data-go="details">← Back to event</button>
      <div class="page-top"><span class="eyebrow">MAKE IT YOURS</span><h1>Choose your seats<span style="color:var(--coral)">.</span></h1><p>Select the seats you want on the seating map.</p></div>
      <div class="seat-layout">
        <div class="panel-card"><div class="seat-stage">STAGE</div><div class="seat-map" id="seatMap" aria-label="Interactive seat selection"></div><div class="seat-legend"><span><i class="seat-dot"></i>Available</span><span><i class="seat-dot selected"></i>Selected</span><span><i class="seat-dot occupied"></i>Unavailable</span></div></div>
        <aside class="panel-card"><span class="eyebrow">YOUR SELECTION</span><h3 id="seatEventName">Event</h3><div class="selected-list" id="selectedSeatList">No seats selected yet.</div><div class="order-total"><span>Total</span><strong id="seatTotal">$0.00</strong></div><button class="search-button full-button" id="continueCheckout" type="button" disabled>Continue to checkout</button><p class="fine-print" style="margin-top:12px">Demo only. Seat availability is not reserved in a database.</p></aside>
      </div>
    </section>

    <!-- ==============================================================
         VIEW: CHECKOUT
         Demo form - no payment is taken
         ============================================================== -->
    <section class="wrap workflow-page" id="checkoutPage" hidden>
      <button class="workflow-back" type="button" data-go="seats">← Back to seats</button>
      <div class="page-top"><span class="eyebrow">ALMOST THERE</span><h1>Checkout<span style="color:var(--coral)">.</span></h1><p>Enter your details and review your order.</p></div>
      <div class="checkout-layout">
        <form class="checkout-form" id="checkoutForm">
          <h2>Your details</h2>
          <div class="form-row"><label>First name<input name="firstName" required autocomplete="given-name" placeholder="First name"></label><label>Last name<input name="lastName" required autocomplete="family-name" placeholder="Last name"></label></div>
          <label>Email address<input name="email" type="email" required autocomplete="email" placeholder="you@example.com"></label>
          <label class="check-row"><input type="checkbox" required> I agree to the terms and conditions.</label>
          <button class="search-button full-button" type="submit">Place demo order</button>
          <p class="fine-print">This is a frontend demonstration. No payment is collected. Add server-side validation and payment processing in your PHP backend.</p>
        </form>
        <aside class="panel-card"><span class="eyebrow">ORDER SUMMARY</span><h3 id="checkoutEventName">Event</h3><div class="selected-list" id="checkoutSeatList"></div><div class="order-total"><span>Total</span><strong id="checkoutTotal">$0.00</strong></div></aside>
      </div>
    </section>

    <!-- ==============================================================
         VIEW: CONFIRMATION
         Summary injected by the checkout submit handler
         ============================================================== -->
    <section class="wrap workflow-page" id="confirmationPage" hidden>
      <div class="confirmation-card"><div class="success-mark">✓</div><span class="eyebrow">YOU'RE ALL SET</span><h1>It's a date<span style="color:var(--coral)">.</span></h1><p>Your demo order has been created. Your tickets are now listed in My Tickets.</p><div class="confirmation-summary" id="confirmationSummary"></div><button class="search-button" type="button" data-go="tickets">View my tickets</button> <button class="pill" type="button" data-go="browse">Discover more events</button></div>
    </section>

    <!-- ==============================================================
         VIEW: MY TICKETS
         List injected by renderTickets()
         ============================================================== -->
    <section class="wrap workflow-page" id="ticketsPage" hidden>
      <div class="page-top"><span class="eyebrow">ALL IN ONE PLACE</span><h1>My tickets<span style="color:var(--coral)">.</span></h1><p>Your upcoming plans and booking details.</p></div>
      <div class="ticket-list" id="myTickets"></div>
    </section>

  </main>

  <!-- ==============================================================
       HTML: FOOTER
       ============================================================== -->
  <footer>
    <div class="wrap">
      <div class="footer-grid">
        <div class="footer-brand"><a class="brand" href="#">EventHub</a><p>Events and tickets, simply. Make room for more of what you love.</p></div>
        <div class="footer-col"><h4>Discover</h4><a href="#events" data-filter="Concert">Concerts</a><a href="#events" data-filter="Sport">Sport</a><a href="#events" data-filter="Theatre">Theatre</a><a href="#events">Conferences</a><a href="#tickets" data-go="tickets">My tickets</a></div>
        <div class="footer-col"><h4>Organisers</h4><a href="#organisers">Create an event</a><a href="#organisers">Ticket types</a><a href="#organisers">Payouts</a><a href="#organisers">Pricing</a></div>
        <div class="footer-col"><h4>About us</h4><a href="#about">About</a><a href="#about">Help centre</a><a href="#about">Terms</a><a href="#about">Privacy</a></div>
      </div>
      <div class="copyright"><span>© 2026 EventHub. All rights reserved.</span><span>Made for nights worth remembering.</span></div>
    </div>
  </footer>

  <!-- ==============================================================
       JS: PAGE SCRIPT
       All behaviour for this page. Sections: config, profile menu, demo data, navigation, browse, details, seats, checkout, tickets, home filters, mobile menu.
       ============================================================== -->
  <script>

// ==============================================================
// JS: CONFIG & SMALL HELPERS
// ==============================================================
// Tickets are kept per signed-in account (demo storage in this browser only).
const TICKET_KEY = 'eventhub_demo_tickets_' + <?= json_encode($user ? (string)(int)$user['UserID'] : 'guest') ?>;

// --- CONST SELL_URL: where "Start selling" leads (profile if signed in, sign-up if not) ---
const SELL_URL = <?= json_encode($user ? 'eventhub_profile.php' : 'eventhub_signup.php') ?>;

// --- FUNC esc(): escapes text before it is placed into HTML strings (prevents XSS) ---
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

// ==============================================================
// JS: PROFILE DROPDOWN
// Opens on click; closes on outside click or Escape
// ==============================================================
const profileBtn = document.getElementById('profileBtn'), profileDropdown = document.getElementById('profileDropdown');
if (profileBtn && profileDropdown) {
  profileBtn.addEventListener('click', e => {
    e.stopPropagation();
    profileDropdown.hidden = !profileDropdown.hidden;
    profileBtn.setAttribute('aria-expanded', String(!profileDropdown.hidden));
  });
  document.addEventListener('click', e => {
    if (profileDropdown.contains(e.target)) return;
    profileDropdown.hidden = true; profileBtn.setAttribute('aria-expanded', 'false');
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') profileDropdown.hidden = true; });
}

// --- JS: "Start selling" button ---
document.getElementById('startSelling').addEventListener('click', () => { location.href = SELL_URL; });

// ==============================================================
// JS: DEMO EVENT DATA
// Sample events used by Browse, Details, Seats and Checkout
// ==============================================================
const events = [
 {id:1,name:'Summer Stage',category:'Concert',date:'14 Jun · 7:00 PM',venue:'Riverside Park',location:'Kamloops, BC',price:35,art:'stage',desc:'An outdoor evening of live music, local performers and a lively summer atmosphere. Bring friends and enjoy a night of music under the open sky.'},
 {id:2,name:'Cup Final',category:'Sport',date:'21 Jun · 6:30 PM',venue:'Tournament Capital Centre',location:'Kamloops, BC',price:28,art:'cup',desc:'Experience the excitement of the cup final. Cheer for your favourite team and enjoy an evening of competitive sport.'},
 {id:3,name:'Open Mic Club',category:'Theatre',date:'28 Jun · 8:00 PM',venue:'The Stage Door',location:'Kamloops, BC',price:15,art:'comedy',desc:'A relaxed night of comedy, live performances and new local talent. Come for the laughs and stay for the atmosphere.'}
];

// --- JS: APP STATE - currentEvent (open event), selectedSeats, currentOrder (last order placed) ---
let currentEvent=null, selectedSeats=[], currentOrder=null;

// --- FUNC money(): formats a number as $12.34 ---
const money=n=>'$'+Number(n).toFixed(2);

// ==============================================================
// JS: VIEW NAVIGATION
// FUNC showPage(name): shows one view, hides the others, and shows the home sections only for "home"
// ==============================================================
function showPage(name){
  const pages=['browsePage','detailsPage','seatsPage','checkoutPage','confirmationPage','ticketsPage'];
  pages.forEach(id=>{const el=document.getElementById(id); if(el) el.hidden=id!==name+'Page';});
  ['.hero','#events','.steps','#organisers'].forEach(selector=>{const el=document.querySelector(selector);if(el)el.hidden=name!=='home';});
  if(name==='browse') renderBrowse();
  if(name==='tickets') renderTickets();
  window.scrollTo({top:0,behavior:'smooth'});
  document.getElementById('mainNav')?.classList.remove('open');
  document.getElementById('menuToggle')?.setAttribute('aria-expanded','false');
}

// --- FUNC goHome(): back to the home sections ---
function goHome(){showPage('home');document.getElementById('mainNav')?.classList.remove('open');}

// --- JS: click routing - [data-go] and [data-open-page] buttons switch views ---
document.addEventListener('click',e=>{
 const go=e.target.closest('[data-go]'); if(go){e.preventDefault();const target=go.dataset.go;if(target==='home')goHome();else showPage(target);return;}
 const open=e.target.closest('[data-open-page]'); if(open){e.preventDefault();showPage(open.dataset.openPage);}
});

// ==============================================================
// JS: BROWSE - rendering & filtering
// FUNC artClass(): picks the artwork CSS class for an event
// ==============================================================
function artClass(art){return art==='stage'?'stage':art==='cup'?'cup':'comedy';}

// --- FUNC eventCard(): HTML for one event card ---
function eventCard(ev){return `<article class="workflow-card"><div class="event-art ${artClass(ev.art)}"><span class="category">${ev.category}</span></div><div class="event-info"><div class="event-date">${ev.date}</div><h3 class="event-title">${ev.name}</h3><div class="event-bottom"><span class="price">From ${money(ev.price)}</span><button class="ticket-btn" type="button" data-event-id="${ev.id}">View event</button></div></div></article>`;}

// --- FUNC renderBrowse(): applies keyword / category / location filters and redraws the grid ---
function renderBrowse(){
 const term=(document.getElementById('browseKeyword').value||'').trim().toLowerCase();
 const category=document.getElementById('browseCategory').value;
 const place=(document.getElementById('browseLocation').value||'').trim().toLowerCase();
 const found=events.filter(ev=>(category==='all'||ev.category===category)&&(!term||(ev.name+' '+ev.category+' '+ev.desc).toLowerCase().includes(term))&&(!place||ev.location.toLowerCase().includes(place)||place==='anywhere'));
 document.getElementById('browseGrid').innerHTML=found.map(eventCard).join('');
 document.getElementById('browseCount').textContent=`${found.length} event${found.length===1?'':'s'} found`;
 document.getElementById('browseEmpty').style.display=found.length?'none':'block';
}

// --- JS: Browse filter wiring (Search button and Enter key) ---
document.getElementById('browseSearch').addEventListener('click',renderBrowse);
['browseKeyword','browseCategory','browseLocation'].forEach(id=>document.getElementById(id).addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();renderBrowse();}}));

// ==============================================================
// JS: EVENT DETAILS
// FUNC openDetails(id): builds the details view for an event and shows it
// ==============================================================
function openDetails(id){currentEvent=events.find(ev=>ev.id===Number(id));if(!currentEvent)return;selectedSeats=[];
 document.getElementById('detailsContent').innerHTML=`<div class="page-top"><span class="eyebrow">${currentEvent.category}</span><h1>${currentEvent.name}<span style="color:var(--coral)">.</span></h1><p>${currentEvent.date} · ${currentEvent.location}</p></div><div class="details-layout"><div><div class="event-art details-art ${artClass(currentEvent.art)}"><span class="category">${currentEvent.category}</span></div><h2 style="font-size:30px;margin-bottom:12px">About this event</h2><p class="details-copy">${currentEvent.desc}</p><div class="details-meta"><div class="meta-box"><small>Date and time</small><strong>${currentEvent.date}</strong></div><div class="meta-box"><small>Venue</small><strong>${currentEvent.venue}</strong></div><div class="meta-box"><small>Location</small><strong>${currentEvent.location}</strong></div><div class="meta-box"><small>Category</small><strong>${currentEvent.category}</strong></div></div></div><aside class="panel-card"><span class="eyebrow">TICKETS</span><h3>${currentEvent.name}</h3><p>Choose your seats to continue with your booking.</p><div class="panel-price">From ${money(currentEvent.price)}</div><button class="search-button full-button" type="button" id="chooseSeatsBtn">Choose seats</button></aside></div>`;
 document.getElementById('chooseSeatsBtn').addEventListener('click',()=>{showPage('seats');renderSeats();});showPage('details');}

// --- JS: clicking a "View event" button opens its details ---
document.addEventListener('click',e=>{const card=e.target.closest('[data-event-id]');if(card)openDetails(card.dataset.eventId);});

// ==============================================================
// JS: SEAT SELECTION
// FUNC renderSeats(): draws an 8x8 seat map (a few seats are marked unavailable)
// ==============================================================
function renderSeats(){if(!currentEvent)return;selectedSeats=[];document.getElementById('seatEventName').textContent=currentEvent.name;const map=document.getElementById('seatMap');map.innerHTML='';
 const unavailable=new Set(['A3','B6','C2','D7','E4','F1','G5','H8']);
 for(let row=0;row<8;row++){for(let col=1;col<=8;col++){const seatId=String.fromCharCode(65+row)+col;const btn=document.createElement('button');btn.type='button';btn.className='seat';btn.textContent=seatId;btn.dataset.seat=seatId;if(unavailable.has(seatId)){btn.classList.add('occupied');btn.disabled=true;btn.setAttribute('aria-label',seatId+' unavailable');}else{btn.setAttribute('aria-label','Select seat '+seatId);btn.addEventListener('click',()=>toggleSeat(seatId,btn));}map.appendChild(btn);}}
 updateSeatSummary();}

// --- FUNC toggleSeat(): select / deselect a seat ---
function toggleSeat(id,btn){if(selectedSeats.includes(id)){selectedSeats=selectedSeats.filter(s=>s!==id);btn.classList.remove('selected');}else{selectedSeats.push(id);btn.classList.add('selected');}updateSeatSummary();}

// --- FUNC updateSeatSummary(): refreshes the seat list, total and Continue button ---
function updateSeatSummary(){document.getElementById('selectedSeatList').textContent=selectedSeats.length?selectedSeats.sort().join(', '):'No seats selected yet.';document.getElementById('seatTotal').textContent=money(selectedSeats.length*(currentEvent?.price||0));document.getElementById('continueCheckout').disabled=!selectedSeats.length;}

// ==============================================================
// JS: CHECKOUT
// Continue button copies the selection into the checkout summary
// ==============================================================
document.getElementById('continueCheckout').addEventListener('click',()=>{if(!selectedSeats.length)return;document.getElementById('checkoutEventName').textContent=currentEvent.name;document.getElementById('checkoutSeatList').textContent='Seats: '+selectedSeats.join(', ');document.getElementById('checkoutTotal').textContent=money(selectedSeats.length*currentEvent.price);showPage('checkout');});

// --- JS: place order - builds the order, saves it to localStorage, shows the confirmation ---
document.getElementById('checkoutForm').addEventListener('submit',e=>{e.preventDefault();if(!currentEvent||!selectedSeats.length)return;const fd=new FormData(e.currentTarget);const order={id:'EH-'+Date.now().toString().slice(-8),eventId:currentEvent.id,eventName:currentEvent.name,category:currentEvent.category,date:currentEvent.date,venue:currentEvent.venue,location:currentEvent.location,art:currentEvent.art,seats:[...selectedSeats],total:selectedSeats.length*currentEvent.price,firstName:fd.get('firstName'),lastName:fd.get('lastName'),email:fd.get('email'),createdAt:new Date().toISOString()};let list=[];try{list=JSON.parse(localStorage.getItem(TICKET_KEY)||'[]');}catch{}list.unshift(order);localStorage.setItem(TICKET_KEY,JSON.stringify(list));currentOrder=order;
 document.getElementById('confirmationSummary').innerHTML=`<div class="order-line"><span>Order number</span><strong>${order.id}</strong></div><div class="order-line"><span>Event</span><strong>${order.eventName}</strong></div><div class="order-line"><span>Date</span><strong>${order.date}</strong></div><div class="order-line"><span>Seats</span><strong>${order.seats.join(', ')}</strong></div><div class="order-total"><span>Total</span><strong>${money(order.total)}</strong></div>`;e.currentTarget.reset();showPage('confirmation');});

// ==============================================================
// JS: MY TICKETS
// FUNC renderTickets(): reads saved orders from localStorage and lists them
// ==============================================================
function renderTickets(){let list=[];try{list=JSON.parse(localStorage.getItem(TICKET_KEY)||'[]');}catch{}const target=document.getElementById('myTickets');if(!list.length){target.innerHTML='<div class="empty-tickets"><h3 style="font:400 25px Gloock,Georgia,serif;color:var(--ink)">No tickets yet</h3><p>Your booked events will appear here.</p><button class="search-button" type="button" data-go="browse">Discover events</button></div>';return;}target.innerHTML=list.map(t=>`<article class="ticket-item"><div class="event-art ${artClass(t.art)}"></div><div><span class="eyebrow">${t.category} · CONFIRMED DEMO ORDER</span><h3>${t.eventName}</h3><p>${t.date} · ${t.venue}</p><p>Seats: ${t.seats.join(', ')} · ${money(t.total)}</p><p>Booked by ${esc(t.firstName)} ${esc(t.lastName)}</p></div><div class="ticket-code">ORDER<br><strong>${t.id}</strong></div></article>`).join('');}

// ==============================================================
// JS: HOME PAGE SEARCH & FILTERS
// Home search form, live filter of the static cards, category links
// ==============================================================
document.getElementById('eventSearch').addEventListener('submit',e=>{e.preventDefault();const keyword=document.getElementById('keyword').value;const category=activeCategory;showPage('browse');document.getElementById('browseKeyword').value=keyword;document.getElementById('browseCategory').value=['Concert','Sport','Theatre'].includes(category)?category:'all';document.getElementById('browseLocation').value=document.getElementById('location').value;renderBrowse();});

// --- JS: home card references and active category ---
const eventCards=[...document.querySelectorAll('#eventGrid .event-card')],eventGrid=document.getElementById('eventGrid'),emptyState=document.getElementById('emptyState');let activeCategory='All';

// --- JS: "Get tickets" on a home card opens that event ---
eventCards.forEach(card=>{const btn=card.querySelector('.ticket-btn');if(btn)btn.addEventListener('click',()=>{const ev=events.find(item=>item.name===btn.dataset.event);if(ev)openDetails(ev.id);});});

// --- FUNC filterLandingEvents(): shows / hides home cards by keyword and category ---
function filterLandingEvents(){const term=document.getElementById('keyword').value.trim().toLowerCase();let visible=0;eventCards.forEach(card=>{const show=(!term||(card.dataset.search+' '+card.querySelector('.event-title').textContent).toLowerCase().includes(term))&&(activeCategory==='All'||card.dataset.category===activeCategory);card.hidden=!show;if(show)visible++;});emptyState.style.display=visible?'none':'block';eventGrid.style.display=visible?'grid':'none';}

// --- JS: wiring - live keyword filter and "View all" link ---
document.getElementById('keyword').addEventListener('input',filterLandingEvents);document.getElementById('viewAll').addEventListener('click',e=>{e.preventDefault();showPage('browse');});

// --- JS: nav category links open Browse with that category ---
document.querySelectorAll('nav a[data-filter]').forEach(link=>link.addEventListener('click',e=>{e.preventDefault();activeCategory=link.dataset.filter;showPage('browse');document.getElementById('browseCategory').value=activeCategory==='All'?'all':activeCategory;renderBrowse();}));

// ==============================================================
// JS: MOBILE MENU - hamburger toggles the nav
// ==============================================================
document.getElementById('menuToggle').addEventListener('click',()=>{const nav=document.getElementById('mainNav');const open=nav.classList.toggle('open');document.getElementById('menuToggle').setAttribute('aria-expanded',String(open));});
</script>
</body>
</html>
