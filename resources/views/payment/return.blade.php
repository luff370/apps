<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支付结果</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <style>
        body {
            font-family: "PingFang SC", Arial, sans-serif;
            background: url('https://img.alicdn.com/imgextra/i2/O1CN01r5yBLw1CJ1McojSnm_!!6000000000064-2-tps-990-400.png') no-repeat center center;
            background-size: cover;
            background-color: #f2f2f2;
            margin: 0;
            padding: 0;
            text-align: center;
        }
        .container {
            background: rgba(255, 255, 255, 0.95);
            margin: 80px auto 0;
            padding: 40px 20px 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        .icon img {
            width: 64px;
            height: 64px;
            margin-bottom: 20px;
        }
        h2 {
            font-size: 22px;
            color: #333;
            margin-bottom: 10px;
        }
        .info {
            margin-top: 20px;
            font-size: 16px;
            color: #666;
        }
        .info span {
            display: block;
            margin: 8px 0;
        }
        .status-box {
            margin-top: 30px;
        }
        .status-message {
            font-size: 18px;
            padding: 10px 15px;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 20px;
        }
        .success { background-color: #e8f5e9; color: #2e7d32; }
        .pending { background-color: #fffde7; color: #f9a825; }
        .error { background-color: #ffebee; color: #c62828; }
        #returnApp {
            display: inline-block;
            padding: 12px 30px;
            font-size: 16px;
            color: #fff;
            background-color: #04c35c;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        #returnApp:hover {
            background-color: #029e4b;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="icon">
        <img id="paymentIcon" src="" alt="支付方式图标">
    </div>
    <h2>支付状态查询</h2>

    <div class="info">
        <span>产品名称：<strong id="productName">-</strong></span>
        <span>支付金额：<strong id="amount">-</strong></span>
        <span>订单号：<strong id="orderNoDisplay">-</strong></span>
    </div>

    <div class="status-box">
        <p id="status" class="status-message pending">正在确认支付结果，请稍候...</p><br>
        <button id="returnApp">返回App</button>
    </div>
</div>

<script>
    $(document).ready(function () {
        let orderNo = "{{ $orderNo }}";
        let payStatus = "{{ $payStatus }}";
        let productName = "{{ $productName }}";
        let amount = "{{ $amount }}";
        let payType = "{{ $payType }}";
        let pollTimer = null;
        let pollCount = 0;
        let maxPollCount = 10; // 查询10次

        $("#productName").text(productName || "未知产品");
        $("#amount").text(amount ? "¥" + amount : "-");
        $("#orderNoDisplay").text(orderNo || "-");

        if (payType === "wechat") {
            $("#paymentIcon").attr("src", "https://img.yzcdn.cn/vant/weapp-pay-wechat.png");
        } else if (payType === "alipay") {
            $("#paymentIcon").attr("src", "https://img.yzcdn.cn/vant/weapp-pay-alipay.png");
        } else {
            $("#paymentIcon").attr("src", "https://img.icons8.com/ios-filled/50/000000/bank-card-back-side.png");
        }

        function updateStatus(newStatus) {
            if (newStatus === "paid") {
                $("#status").removeClass("pending error").addClass("success").text("✅ 支付成功！");
                clearInterval(pollTimer);
                setTimeout(function () {
                    window.location.href = `yourapp://payment-status?order_no=${orderNo}`;
                }, 3000);
            } else if (newStatus === "error") {
                $("#status").removeClass("pending success").addClass("error").text("❌ 支付失败，请重试！");
                clearInterval(pollTimer);
            } else {
                $("#status").removeClass("success error").addClass("pending").text("⚠️ 支付结果查询中...");
            }
        }

        updateStatus(payStatus);

        pollTimer = setInterval(function () {
            if (pollCount >= maxPollCount) {
                clearInterval(pollTimer); // 超过次数，静默停止
                return;
            }
            pollCount++;

            $.ajax({
                url: `/api/payment/order/status?order_no=${orderNo}`,
                type: "POST",
                success: function (res) {
                    if (res && res.data) {
                        updateStatus(res.data.pay_status);
                    }
                },
                error: function () {
                    console.error("查询失败");
                }
            });
        }, 1000);

        $("#returnApp").click(function () {
            window.location.href = `yourapp://payment-status?order_no=${orderNo}`;
        });
    });
</script>

</body>
</html>
