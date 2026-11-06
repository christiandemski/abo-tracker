<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
  @page { margin: 25mm 22mm 20mm 25mm; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; line-height: 1.5; color: #111; }
  p { margin: 0 0 10pt 0; white-space: pre-wrap; }
</style>
</head>
<body>
@foreach (preg_split('/\n{2,}/', trim($text)) as $paragraph)
<p>{{ $paragraph }}</p>
@endforeach
</body>
</html>
