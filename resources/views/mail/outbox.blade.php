{{-- Wiersz outboxu wyrenderowany w brandowalnym komponencie maila. --}}
<x-mail.message
    :brand="$brand"
    :preheader="$preheader"
    :heading="$heading"
    :greeting="$greeting"
    :lines="$lines"
    :actionText="$actionText"
    :actionUrl="$actionUrl"
    :outroLines="$outroLines"
/>
