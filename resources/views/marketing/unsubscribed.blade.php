<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex" />
<title>{{ $valid ? 'Odjavljeni ste' : 'Link nije važeći' }} · Meva</title>
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F2EFE8;
       font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#24342C;padding:24px}
  .card{background:#fff;border-radius:20px;padding:44px 36px;max-width:440px;width:100%;text-align:center;
        box-shadow:0 20px 60px rgba(36,52,44,.08)}
  .mark{letter-spacing:6px;font-size:14px;color:#8A9A8B;margin-bottom:22px}
  h1{font-family:Georgia,serif;font-weight:400;font-size:27px;line-height:34px;margin:0 0 12px}
  p{font-size:15px;line-height:25px;color:#6B7A71;margin:0 0 10px}
  .mail{color:#24342C;font-weight:600}
  a.btn{display:inline-block;margin-top:22px;padding:14px 30px;border-radius:999px;background:#24342C;
        color:#fff;text-decoration:none;font-size:15px;font-weight:600}
</style>
</head>
<body>
<div class="card">
  <div class="mark">MEVA</div>
  @if ($valid)
    <h1>Odjavljeni ste</h1>
    <p>Više vam nećemo slati marketinške poruke na <span class="mail">{{ $email }}</span>.</p>
    <p>Potvrde o porudžbinama i dalje stižu, njih ne isključujemo.</p>
  @else
    <h1>Link nije važeći</h1>
    <p>Ovaj link za odjavu je neispravan ili izmenjen. Odgovorite na naš mejl i odjavićemo vas ručno.</p>
  @endif
  <a class="btn" href="{{ $shop }}">Nazad na prodavnicu</a>
</div>
</body>
</html>
