<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>取引完了のお知らせ</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e74c3c;
        }
        .header h1 {
            color: #e74c3c;
            margin: 0;
            font-size: 24px;
        }
        .content {
            margin-bottom: 30px;
        }
        .item-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .item-info h3 {
            color: #e74c3c;
            margin-top: 0;
        }
        .price {
            font-size: 18px;
            font-weight: bold;
            color: #e74c3c;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            background-color: #e74c3c;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>取引完了のお知らせ</h1>
        </div>

        <div class="content">
            <p>{{ $sellerName }} 様</p>

            <p>いつもフリマアプリをご利用いただき、ありがとうございます。</p>

            <p>以下の商品の取引が完了いたしました。</p>

            <div class="item-info">
                <h3>商品情報</h3>
                <p><strong>商品名:</strong> {{ $itemName }}</p>
                <p><strong>価格:</strong> <span class="price">¥{{ number_format($itemPrice) }}</span></p>
                <p><strong>購入者:</strong> {{ $buyerName }} 様</p>
            </div>

            <p>購入者の {{ $buyerName }} 様が取引完了の確認を行いました。</p>

            <p>今回の取引はこれで完了となります。お疲れ様でした！</p>

            <p>取引相手を評価することで、より良いフリマ環境の構築にご協力いただけます。</p>

            <div style="text-align: center;">
                <a href="{{ url('/') }}" class="button">サイトにアクセス</a>
            </div>
        </div>

        <div class="footer">
            <p>このメールは自動送信されています。</p>
            <p>ご不明な点がございましたら、サポートまでお問い合わせください。</p>
            <p>&copy; {{ date('Y') }} フリマアプリ. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
