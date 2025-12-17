<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>授权成功</title>
    <style>
        body { font-family:sans-serif; text-align:center; padding-top:100px; }
        img { width:80px; height:80px; border-radius:50%; margin-bottom:15px; }
        .success { color:green; font-size:22px; }
    </style>
</head>
<body>
@if($avatar)
    <img src="{{ $avatar }}" alt="avatar">
@endif
<div class="success">✅ 授权成功</div>
<p>欢迎 {{ $nick }}</p>
<script>
    // 如果是App内WebView，自动关闭授权页
    if (window.AlipayJSBridge) {
        AlipayJSBridge.call('closeWebview');
    }
</script>
</body>
</html>
