<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f4f1ea;font-family:Helvetica,Arial,sans-serif;color:#1c1b19">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden">
    <tr>
        <td style="padding:28px 32px;background:#1c1b19;color:#f4f1ea">
            <div style="font-size:13px;letter-spacing:2px;text-transform:uppercase;opacity:.7">EventPass</div>
            <div style="font-size:24px;font-weight:bold;margin-top:6px">{{ $order->event->title }}</div>
            <div style="margin-top:6px;opacity:.85">{{ $order->event->starts_at->format('d.m.Y H:i') }} · {{ $order->event->venue }}, {{ $order->event->city }}</div>
        </td>
    </tr>
    <tr>
        <td style="padding:24px 32px">
            <p>Hi {{ $order->user->name }}, your payment went through. Show these codes at the entrance.</p>
            @foreach ($order->tickets as $ticket)
                <table role="presentation" width="100%" style="border:1px dashed #c9c2b4;border-radius:10px;margin:16px 0">
                    <tr>
                        <td style="padding:16px;width:150px">
                            <img src="{{ $message->embedData(\App\Support\QrCode::png($ticket->code), $ticket->code.'.png', 'image/png') }}" width="140" height="140" alt="QR code">
                        </td>
                        <td style="padding:16px;vertical-align:top">
                            <div style="font-weight:bold;font-size:16px">{{ $ticket->ticketType->name }}</div>
                            <div style="font-family:monospace;font-size:12px;margin-top:8px;word-break:break-all">{{ $ticket->code }}</div>
                        </td>
                    </tr>
                </table>
            @endforeach
            <p><a href="{{ $orderUrl }}" style="color:#c2410c">Open the order in EventPass</a></p>
        </td>
    </tr>
</table>
</body>
</html>
