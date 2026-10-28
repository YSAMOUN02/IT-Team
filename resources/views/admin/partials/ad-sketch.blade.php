{{-- Small page sketch with the ad position highlighted. $where = top|middle|listing|article_top|article_bottom|footer --}}
<svg class="ad-sketch" viewBox="0 0 120 84" aria-hidden="true">
    <rect x="1" y="1" width="118" height="82" rx="6" class="pg"/>
    <rect x="1" y="1" width="118" height="10" rx="6" class="hd"/>
    @switch($where)
        @case('top')
            <rect x="10" y="15" width="100" height="10" rx="2" class="ad"/>
            <rect x="10" y="30" width="60" height="5" rx="2" class="tx"/><rect x="10" y="39" width="45" height="4" rx="2" class="tx"/>
            <rect x="10" y="50" width="30" height="20" rx="3" class="bx"/><rect x="45" y="50" width="30" height="20" rx="3" class="bx"/><rect x="80" y="50" width="30" height="20" rx="3" class="bx"/>
            @break
        @case('middle')
            <rect x="10" y="15" width="30" height="16" rx="3" class="bx"/><rect x="45" y="15" width="30" height="16" rx="3" class="bx"/><rect x="80" y="15" width="30" height="16" rx="3" class="bx"/>
            <rect x="10" y="36" width="100" height="12" rx="2" class="ad"/>
            <rect x="10" y="53" width="48" height="5" rx="2" class="tx"/><rect x="10" y="62" width="40" height="4" rx="2" class="tx"/><rect x="66" y="53" width="44" height="22" rx="3" class="bx"/>
            @break
        @case('listing')
            <rect x="10" y="15" width="50" height="6" rx="2" class="tx"/>
            <rect x="10" y="25" width="100" height="10" rx="2" class="ad"/>
            <rect x="10" y="40" width="30" height="20" rx="3" class="bx"/><rect x="45" y="40" width="30" height="20" rx="3" class="bx"/><rect x="80" y="40" width="30" height="20" rx="3" class="bx"/>
            <rect x="10" y="64" width="30" height="12" rx="3" class="bx"/><rect x="45" y="64" width="30" height="12" rx="3" class="bx"/><rect x="80" y="64" width="30" height="12" rx="3" class="bx"/>
            @break
        @case('article_top')
            <rect x="25" y="15" width="70" height="6" rx="2" class="tx"/>
            <rect x="25" y="25" width="70" height="14" rx="3" class="bx"/>
            <rect x="25" y="43" width="70" height="10" rx="2" class="ad"/>
            <rect x="25" y="57" width="70" height="3" rx="1.5" class="tx"/><rect x="25" y="63" width="62" height="3" rx="1.5" class="tx"/><rect x="25" y="69" width="66" height="3" rx="1.5" class="tx"/>
            @break
        @case('article_bottom')
            <rect x="25" y="15" width="70" height="6" rx="2" class="tx"/>
            <rect x="25" y="25" width="70" height="3" rx="1.5" class="tx"/><rect x="25" y="31" width="62" height="3" rx="1.5" class="tx"/><rect x="25" y="37" width="66" height="3" rx="1.5" class="tx"/><rect x="25" y="43" width="58" height="3" rx="1.5" class="tx"/>
            <rect x="25" y="52" width="70" height="10" rx="2" class="ad"/>
            <rect x="25" y="67" width="20" height="10" rx="3" class="bx"/><rect x="50" y="67" width="20" height="10" rx="3" class="bx"/><rect x="75" y="67" width="20" height="10" rx="3" class="bx"/>
            @break
        @default
            <rect x="10" y="15" width="30" height="18" rx="3" class="bx"/><rect x="45" y="15" width="30" height="18" rx="3" class="bx"/><rect x="80" y="15" width="30" height="18" rx="3" class="bx"/>
            <rect x="10" y="39" width="70" height="4" rx="2" class="tx"/>
            <rect x="10" y="49" width="100" height="10" rx="2" class="ad"/>
            <rect x="1" y="64" width="118" height="19" rx="0" class="ft"/>
    @endswitch
</svg>
