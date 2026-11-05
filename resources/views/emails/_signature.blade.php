{{-- The signature under an email: $signatureText, with the parent's $logo, $font and $html. --}}
@if (filled($signatureText))
    <tr><td style="padding-top:22px;font-family:{!! $font !!}">
        <table role="presentation" cellpadding="0" cellspacing="0" style="border-top:2px solid #5f59f7;font-family:{!! $font !!}">
            <tr>
                @if ($logo)
                    <td valign="top" style="padding:14px 14px 0 0"><img src="{{ $logo }}" width="44" height="44" alt="" style="display:block;border-radius:50%;border:0"></td>
                @endif
                <td valign="top" style="padding-top:12px;font-size:13.5px;line-height:1.65;color:#4f5775;font-family:{!! $font !!}">{!! $html($signatureText) !!}</td>
            </tr>
        </table>
    </td></tr>
@endif
