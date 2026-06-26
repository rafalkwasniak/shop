{{-- Wrapper podglądu: renderuje generyczny komponent maila z danymi z rejestru. --}}
<x-mail.message
    :brand="$brand"
    :preheader="$data['preheader'] ?? null"
    :heading="$data['heading'] ?? null"
    :greeting="$data['greeting'] ?? null"
    :lines="$data['lines'] ?? []"
    :actionText="$data['actionText'] ?? null"
    :actionUrl="$data['actionUrl'] ?? null"
    :outroLines="$data['outroLines'] ?? []"
/>
