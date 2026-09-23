<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
        <style>
        body {
            font-family: "Times New Roman", serif;
            font-size: 12pt;
            margin: 10px;
            padding: 20px;
        }
        h1, h2, h3, h4, h5 {
            margin: 10px 0;
            text-align: center;
        }
        p, li {
            margin: 0;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .contact-info {
            text-align: center;
            font-size: 10pt;
        }
        .date-time-right {
            text-align: right;
        }
        .invoice-details {
            margin: 20px 0;
            font-size: 10pt;
        }
        .poppins-bold {
        font-family: "Poppins", sans-serif;
        font-weight: 700;
        font-style: normal;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #000;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        .grand-total {
            text-align: right;
        }
        .warranty-policy {
            margin: 20px 0;
            font-size: 10pt;
        }
        .terms {
            margin: 20px 0;
            font-size: 10pt;
            text-align: center;
        }
        .footer {
            margin-top: 10px;
            text-align: center;
            font-size: 10pt;
        }
        .print-footer {
            display: none;
        }
        @media print {
        body {
            margin: 10px;
            padding: 5px;
        }
        .header, .footer {
            page-break-inside: avoid;
        }
        .print-footer {
            display: block;
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 9pt;
            background-color: #fff; /* Ensure visibility if printed on colored paper */
            padding: 5px 0;
        }
        .poppins-bold {
        font-family: "Poppins", sans-serif;
        font-weight: 700;
        font-style: normal;
        }
    }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="poppins-bold">Gadget & Phones</h1>
        <h5 style="margin-top: 30px !important;font-size: 18px;">Purchase Proof</h5>
        <p style="font-size: 10pt;!important;font-style: italic;">Shop NO: 18, Shena Kollan Market Cantonment, Cumilla</p>
        <div class="contact-info" style="font-style: italic;margin-top: 10px;!important;">
            <p><i><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" class="bi bi-person" viewBox="0 0 16 16">
                <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4m-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10s-3.516.68-4.168 1.332c-.678.678-.83 1.418-.832 1.664z"/>
              </svg></i> MD. Anamul Haq</p>
            <p><i><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" class="bi bi-telephone" viewBox="0 0 16 16">
                <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.6 17.6 0 0 0 4.168 6.608 17.6 17.6 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.68.68 0 0 0-.58-.122l-2.19.547a1.75 1.75 0 0 1-1.657-.459L5.482 8.062a1.75 1.75 0 0 1-.46-1.657l.548-2.19a.68.68 0 0 0-.122-.58zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877z"/>
              </svg></i> Shop: 01302-983275</p>
            <p><i><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" class="bi bi-telephone" viewBox="0 0 16 16">
                <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.6 17.6 0 0 0 4.168 6.608 17.6 17.6 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.68.68 0 0 0-.58-.122l-2.19.547a1.75 1.75 0 0 1-1.657-.459L5.482 8.062a1.75 1.75 0 0 1-.46-1.657l.548-2.19a.68.68 0 0 0-.122-.58zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877z"/>
              </svg></i> Personal: 01612-677489</p>
            <p><i><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" class="bi bi-envelope" viewBox="0 0 16 16">
                <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1zm13 2.383-4.708 2.825L15 11.105zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741M1 11.105l4.708-2.897L1 5.383z"/>
              </svg></i> Email: juwelanamul21@gmail.com</p>
        </div>
    </div>
    <div class="invoice-details" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <p>Customer: {{ $sell->customer_name }}</p>
            <p>Customer Phone: {{ $sell->customer_phone }}</p>
            <p>Customer Address: {{ $sell->customer_address }}</p>

        </div>
        <div style="text-align: right;">
            <p>Invoice #: {{ $sell->invoice }}</p>
            <p>Purchase Date: {{ $sell->updated_at->format('d/m/Y') ?? $sell->created_at->format('d/m/Y') }}</p>
            <p>Purchase Time: {{ $sell->updated_at->format('g:i A') ?? $sell->created_at->format('g:i A') }}</p>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th>IMEI</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sell->sellDetails as $index => $details)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $details->product->product_name ?? null }} - ({{ $details->product->category->name ?? null }}-{{ $details->product->brand->name ?? null }})</td>
                    <td>{{ $details->imei ?? 'N/A' }}</td>
                    <td>{{ $details->quantity }}</td>
                    <td>{{ number_format(round($details->selling_price / $details->quantity)) }} BDT</td>
                    <td>{{ number_format(round($details->selling_price)) }} BDT</td>
                </tr>
            @endforeach
            <tr>
                <th class="grand-total" colspan="5">Grand Total:</th>
                <td>{{ number_format(round($sell->grand_total)) }} BDT</td>
            </tr>
        </tbody>
    </table>

    <div class="terms">
        <h4>Gadget & Phones Warenty And Replacement Policy</h4>
        <p>For All product General Terms & condition:</p>
        <p>- All product Has 7 days Replacement.</p>
        <p>- Official Product 7 days Replacement.</p>
        <p>- Storage, Color or Model Change within 7 days 20% (Imported) & 30% (official) Will be deducted from the present price</p>
        <p>- Replacement guarantee won't cover if any accidental damage happened.</p>
        <p>- No Warranty for in boxed Accessories.</p>
        <p>- Liquid damage not covered under warranty.</p>
        <p>- Before you leave shop, kindly check product properly. For any cosmetic dent, Scratch or Repair Outside/ Water Proof issue Gadget & Phones won't accept any complaints</p>
    </div>

    <div class="footer">
        <div class="invoice-details" style="display: flex; justify-content: space-between; align-items: center; margin-top: 50px;">
            <div style="text-align: center;">
                <div style="width: 150px; border-bottom: 1px solid #000; margin: 10px auto;"></div>
                <p>Shop Signature</p>
            </div>
            <div style="text-align: center;">
                <div style="width: 150px; border-bottom: 1px solid #000; margin: 10px auto;"></div>
                <p>Customer Signature</p>
            </div>
        </div>
    </div>

    <!-- Footer for print only -->
    <div class="print-footer">
        <p>System Developed By: debugvision.com | Email: contact@debugvision.com</p>
    </div>
</body>
</html>
