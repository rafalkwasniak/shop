// „Popraw przez AI": wysyła treść pola do endpointu redakcji i wstawia poprawiony
// wynik z powrotem. Limit użyć per pole/ładowanie strony pilnuje atrybut
// data-ai-uses. Zero zależności; korzysta z window.showToast (resources/js/app.js).
//
// Świadome edytora Trix: gdy cel to ukryte pole spięte z <trix-editor>, wysyłamy
// HTML (Trix synchronizuje go do inputa) i ładujemy poprawiony HTML z powrotem,
// więc formatowanie zostaje zachowane. Serwer dobiera tryb (html/tekst) per pole.
// Wzorzec przeniesiony z kociaczek.com.pl.
//
// DZIELENIE NA FRAGMENTY: korekta trwa tyle, ile zajmuje PRZEPISANIE tekstu na
// wyjściu, więc czas rośnie liniowo z długością pola — całego regulaminu nie da
// się poprawić jednym wywołaniem, bo przekroczyłoby timeout. Tniemy więc treść
// tutaj, po stronie przeglądarki, i wysyłamy fragmenty po kolei. Robienie tego
// na serwerze nic by nie dało: jedno żądanie HTTP dalej trwałoby minuty.
//
// Ta sama ścieżka obowiązuje wszystkie pola — krótki tekst wychodzi jako jeden
// fragment. Każde pole z <x-rich-editor> dostaje to automatycznie.

const CHUNK_CHARS_FALLBACK = 1200;

// Ile fragmentów leci naraz. Czekamy wtedy tyle, ile trwa najdłuższy, a nie ich
// suma — na opisie produktu to różnica między 19 a 10 sekundami. Okno jest wąskie
// celowo: kilkanaście jednoczesnych wywołań to lawina kosztu w jednej sekundzie.
const CONCURRENCY = 3;

function trixFor(id) {
    return document.querySelector('trix-editor[input="' + id + '"]');
}

// Treść wysyłana do AI: dla Trix to HTML z ukrytego inputa, dla zwykłego pola jego wartość.
function payload(target) {
    return (target.value || '').trim();
}

// Czy pole jest puste — dla Trix liczy się widoczny tekst, nie same znaczniki.
function isBlank(target) {
    const trix = trixFor(target.id);
    const visible = trix ? trix.editor.getDocument().toString() : target.value;
    return (visible || '').trim() === '';
}

// Wstaw wynik: dla Trix ładujemy HTML (zachowuje formatowanie), dla zwykłego pola — wartość.
function writeValue(target, text) {
    const trix = trixFor(target.id);
    if (trix) trix.editor.loadHTML(text);
    else target.value = text;
}

// Zdejmuje opakowanie z odpowiedzi modelu, żeby dało się skleić kawałki jednego
// bloku z powrotem w jeden. Model zwraca fragment tak, jak go dostał — w <div>.
function unwrap(html) {
    const body = new DOMParser().parseFromString(html, 'text/html').body;

    return body.children.length === 1 ? body.children[0].innerHTML : body.innerHTML;
}

// Tnie zawartość JEDNEGO bloku po <br>. Bez tego wklejony regulamin — który Trix
// trzyma jako jeden <div> z dwustoma <br> w środku — szedłby w całości i wracał
// dopiero po kilku minutach albo wcale. Każda paczka dostaje z powrotem to samo
// opakowanie, żeby model widział poprawny HTML.
function splitBlock(element, limit, group) {
    const outer = element.outerHTML;
    const open = outer.slice(0, outer.indexOf('>') + 1);
    const close = `</${element.tagName.toLowerCase()}>`;
    const lines = element.innerHTML.split(/<br\s*\/?>/i);

    const groups = [];
    let buffer = [];
    let length = 0;

    lines.forEach((line) => {
        // Pojedyncza linia dłuższa niż limit leci sama — cięcie w środku zdania
        // rozbijałoby sens i model gubiłby wątek.
        if (buffer.length && length + line.length > limit) {
            groups.push(buffer.join('<br>'));
            buffer = [];
            length = 0;
        }
        buffer.push(line);
        length += line.length + 4;
    });

    if (buffer.length) groups.push(buffer.join('<br>'));

    return groups.map((inner) => ({
        send: open + inner + close,
        group,
        wrapper: { open, close },
    }));
}

// Tnie HTML na fragmenty: najpierw po blokach najwyższego poziomu (akapit,
// nagłówek, lista), a blok większy niż limit — dodatkowo w środku, po <br>.
function splitHtml(html, limit) {
    const body = new DOMParser().parseFromString(html, 'text/html').body;
    const blocks = Array.from(body.children);

    // Goła treść bez bloków (albo coś, czego nie umiemy podzielić) — jeden fragment.
    if (!blocks.length) return [{ send: html, group: null, wrapper: null }];

    const chunks = [];
    let buffer = '';

    const flush = () => {
        if (buffer) chunks.push({ send: buffer, group: null, wrapper: null });
        buffer = '';
    };

    blocks.forEach((element, index) => {
        const piece = element.outerHTML;

        if (piece.length > limit) {
            flush();
            splitBlock(element, limit, index).forEach((chunk) => chunks.push(chunk));

            return;
        }

        if (buffer && buffer.length + piece.length > limit) flush();
        buffer += piece;
    });

    flush();

    return chunks.length ? chunks : [{ send: html, group: null, wrapper: null }];
}

// Składa wyniki z powrotem. Kawałki pochodzące z jednego bloku wracają do jednego
// bloku — inaczej podzielony regulamin wróciłby jako kilkanaście osobnych akapitów,
// czyli z cicho przebudowaną strukturą, o którą nikt nie prosił.
function reassemble(chunks, results, separator) {
    const out = [];
    let i = 0;

    while (i < chunks.length) {
        const { group, wrapper } = chunks[i];

        if (group === null) {
            out.push(results[i]);
            i += 1;
            continue;
        }

        const parts = [];

        while (i < chunks.length && chunks[i].group === group) {
            parts.push(unwrap(results[i]));
            i += 1;
        }

        out.push(wrapper.open + parts.join('<br>') + wrapper.close);
    }

    return out.join(separator);
}

// Zwykły tekst tniemy po akapitach (pusta linia), tą samą zasadą co HTML.
function splitText(text, limit) {
    const paragraphs = text.split(/\n{2,}/);
    const chunks = [];
    let buffer = '';

    paragraphs.forEach((paragraph) => {
        if (buffer && buffer.length + paragraph.length > limit) {
            chunks.push(buffer);
            buffer = '';
        }
        buffer += (buffer ? '\n\n' : '') + paragraph;
    });

    if (buffer) chunks.push(buffer);

    return (chunks.length ? chunks : [text]).map((send) => ({ send, group: null, wrapper: null }));
}

function splitContent(target, text, limit) {
    return trixFor(target.id) ? splitHtml(text, limit) : splitText(text, limit);
}

// Identyfikator JEDNEGO kliknięcia „Popraw przez AI". Wszystkie fragmenty tego
// samego tekstu niosą go razem ze sobą, dzięki czemu serwer liczy do limitu
// ZADANIE, a nie każdy fragment z osobna (patrz App\Services\AiQuota).
function newTaskId() {
    return (crypto.randomUUID && crypto.randomUUID())
        || `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

// Utnij z końca niedokończony znacznik („<stro") i encję („&am") — strumień
// przerywa w dowolnym miejscu, a edytor pokazałby taki ogon dosłownie. Tylko
// do podglądu na żywo; ostateczny tekst przychodzi w zdarzeniu końcowym cały.
function trimIncomplete(html) {
    return html.replace(/<[^>]*$/, '').replace(/&[a-zA-Z0-9#]{0,8}$/, '');
}

// Efekt „AI zabiera tekst do poprawy": treść znika od KOŃCA, po kilka znaków
// na tyknięcie, w ~2,5 s — a chwilę później poprawiona wersja pisze się w
// puste pole od góry. Przy okazji maskuje fazę myślenia modelu (kilkanaście
// sekund przed pierwszym tokenem), w trakcie której nic by się nie działo.
// Trix wymazuje własnym mechanizmem (zaznaczenie od końca + backspace), więc
// formatowanie znika naturalnie razem z tekstem; zwykłe pole przycina wartość.
function eraseBackwards(target, duration = 2500) {
    return new Promise((resolve) => {
        const trix = trixFor(target.id);
        const interval = 30;
        const length = trix ? trix.editor.getDocument().getLength() : (target.value || '').length;
        const step = Math.max(1, Math.ceil(length / (duration / interval)));

        const timer = setInterval(() => {
            try {
                if (trix) {
                    // Pusty dokument Trixa ma długość 1 (końcowy znak nowej linii).
                    const len = trix.editor.getDocument().getLength();
                    if (len <= 1) { clearInterval(timer); resolve(); return; }
                    trix.editor.setSelectedRange([Math.max(0, len - 1 - step), len - 1]);
                    trix.editor.deleteInDirection('backward');
                } else {
                    if (!target.value) { clearInterval(timer); resolve(); return; }
                    target.value = target.value.slice(0, -step);
                }
            } catch (error) {
                // Efekt jest ozdobą — gdy edytor odmówi, czyścimy pole od razu
                // i jedziemy dalej, zamiast wywracać całą poprawę.
                writeValue(target, '');
                clearInterval(timer);
                resolve();
            }
        }, interval);
    });
}

// Czyta strumień SSE ze zwykłego fetch() — EventSource nie umie POST-a, więc
// linie „data: {json}" parsujemy sami. Kończy się zdarzeniem {done, text,
// remaining} (ten sam kształt co odpowiedź JSON); zdarzenie {error} zamienia
// się w odrzucenie o kształcie Response (status + json()), żeby obsługa
// błędów niżej była wspólna dla obu transportów.
async function readStream(response, onDelta) {
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    for (;;) {
        const { done, value } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });

        let cut;
        while ((cut = buffer.indexOf('\n')) !== -1) {
            const line = buffer.slice(0, cut).trim();
            buffer = buffer.slice(cut + 1);
            if (!line.startsWith('data:')) continue;

            const event = JSON.parse(line.slice(5));
            if (event.error) throw { status: event.status, json: async () => ({ message: event.message }) };
            if (event.done) return event;
            if (event.delta) onDelta(event.delta);
        }
    }

    // Połączenie padło w pół strumienia (restart PHP, sieć) — dla wołającego
    // to zwykła awaria fragmentu: oryginał zostaje.
    throw new Error('Strumień urwał się przed zakończeniem.');
}

// Jeden fragment → jedno wywołanie endpointu. Przy strumieniu kawałki tekstu
// lecą do onDelta w trakcie pisania; serwer może strumienia ODMÓWIĆ
// (wyłącznik awaryjny AI_STREAMING) i wtedy wraca zwykły JSON — poznajemy to
// po Content-Type, nie po tym, o co prosiliśmy.
function sendChunk(button, chunk, taskId, onDelta) {
    const token = document.querySelector('meta[name="csrf-token"]');
    const streaming = button.hasAttribute('data-ai-stream');

    return fetch(button.getAttribute('data-ai-url'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
        },
        body: JSON.stringify({
            field: button.getAttribute('data-ai-field'),
            text: chunk,
            task_id: taskId,
            stream: streaming,
        }),
    }).then((response) => {
        if (!response.ok) return Promise.reject(response);

        const type = response.headers.get('Content-Type') || '';

        return streaming && type.includes('text/event-stream')
            ? readStream(response, onDelta)
            : response.json();
    });
}

async function improveWithAi(button) {
    const target = document.getElementById(button.getAttribute('data-ai-target'));
    if (!target || button.disabled) return;

    const max = parseInt(button.getAttribute('data-ai-max'), 10) || 3;
    let used = parseInt(button.getAttribute('data-ai-uses') || '0', 10);
    if (used >= max) return;

    if (isBlank(target)) {
        window.showToast('Najpierw wpisz treść do poprawy.', 'error');
        return;
    }

    const labelEl = button.querySelector('[data-ai-text]');
    const originalLabel = button.getAttribute('data-ai-label');
    const limit = parseInt(button.getAttribute('data-ai-chunk'), 10) || CHUNK_CHARS_FALLBACK;
    const chunks = splitContent(target, payload(target), limit);

    button.disabled = true;

    // Postęp: fragmenty pokazują, ile zostało, sekundy — że cokolwiek się dzieje.
    // Bez sekund etykieta potrafi stać nieruchomo przez minutę i wygląda jak
    // zawieszona strona, co kusi do odświeżenia w połowie roboty.
    const startedAt = Date.now();
    let done = 0;
    const tick = () => {
        const seconds = Math.round((Date.now() - startedAt) / 1000);
        // Numer fragmentu W TRAKCIE, nie liczba ukończonych — inaczej licznik
        // startuje od „0 z 3", a „3 z 3" nigdy się nie pokazuje, bo tuż po nim
        // pętla się kończy. Ograniczenie do liczby fragmentów pilnuje, żeby po
        // ostatnim nie wyskoczyło „4 z 3".
        const current = Math.min(done + 1, chunks.length);
        labelEl.textContent = chunks.length > 1
            ? `Poprawiam… ${current} z ${chunks.length} · ${seconds} s`
            : `Poprawiam… ${seconds} s`;
    };
    tick();
    const ticker = setInterval(tick, 1000);

    // Wynik każdego fragmentu ląduje na swoim miejscu; brak wyniku = zostaje
    // oryginał, więc częściowa awaria nie kasuje tego, co się udało.
    const results = chunks.map((chunk) => chunk.send);
    // Wspólny dla wszystkich fragmentów — dla limitu to JEDNO zadanie.
    const taskId = newTaskId();
    let failed = false;
    let failure = null;
    let next = 0;

    // Pisanie na żywo (streaming): fragmenty lecą RÓWNOLEGLE (czas ściany jak
    // przy ścieżce JSON), ale w polu pisze zawsze tylko NAJWCZEŚNIEJSZY
    // nieukończony. Kawałki późniejszych fragmentów czekają w buforach i
    // odsłaniają się, gdy przyjdzie ich kolej — tekst płynie jedną falą,
    // od góry do dołu, w pole opróżnione efektem wymazywania.
    //
    // Wersja „fragmenty po kolei" przetestowana i odrzucona: każdy fragment
    // płaci osobno fazę myślenia modelu, więc sekwencja wyglądała jak dwa
    // osobne pisania przedzielone martwą ciszą i podwajała czas całkowity.
    const streaming = button.hasAttribute('data-ai-stream');
    const isTrix = !!trixFor(target.id);
    const buffers = chunks.map(() => '');
    const finals = chunks.map(() => null);
    // Osobna warstwa WIDOKU: po wymazaniu pole zapełnia się wyłącznie tym, co
    // już poprawione (sloty startują puste). `results` zostaje warstwą PRAWDY
    // do ostatecznego zapisu — trzyma oryginały na wypadek awarii fragmentu.
    const display = chunks.map(() => '');
    let reveal = 0;
    let lastPaint = 0;
    let erasing = false;

    const paint = (force) => {
        // W trakcie wymazywania nie odrysowujemy — pisanie zacznie się w
        // pustym polu zaraz po nim (paint(true) w domknięciu efektu).
        if (erasing) return;

        const now = Date.now();
        // Nie częściej niż co 250 ms — loadHTML przy każdym tokenie migotałoby.
        if (!force && now - lastPaint < 250) return;
        lastPaint = now;
        writeValue(target, reassemble(chunks, display, isTrix ? '' : '\n\n'));
    };

    // Przesuń odsłanianie: ukończone fragmenty wchodzą w wersji ostatecznej,
    // a kolejny w trakcie pisania pokazuje od razu to, co zdążył zbuforować.
    const advance = () => {
        while (reveal < chunks.length && finals[reveal] !== null) {
            display[reveal] = finals[reveal];
            reveal += 1;
        }

        if (reveal < chunks.length && buffers[reveal] !== '') {
            display[reveal] = isTrix ? trimIncomplete(buffers[reveal]) : buffers[reveal];
        }

        paint(true);
    };

    // Efekt startuje razem z żądaniami — wymazywanie i myślenie modelu biegną
    // RÓWNOLEGLE, więc ozdoba nie dokłada ani sekundy do całości.
    let erased = Promise.resolve();
    if (streaming) {
        erasing = true;
        erased = eraseBackwards(target).then(() => {
            erasing = false;
            paint(true);
        });
    }

    const worker = async () => {
        while (!failed) {
            const index = next;
            next += 1;
            if (index >= chunks.length) return;

            try {
                const answer = await sendChunk(button, chunks[index].send, taskId, (delta) => {
                    buffers[index] += delta;

                    if (index === reveal) {
                        display[index] = isTrix ? trimIncomplete(buffers[index]) : buffers[index];
                        paint(false);
                    }
                });
                // Wersja OSTATECZNA fragmentu — serwer mógł zdjąć z niej
                // opakowanie ```, którego kawałki nie znały.
                finals[index] = answer.text;
                results[index] = answer.text;
                if (streaming && index === reveal) advance();
                // Serwer odsyła stan puli — zbijamy licznik od razu, zamiast
                // czekać na odświeżenie strony.
                if (window.setAiQuota) window.setAiQuota(answer.remaining);
                done += 1;
                tick();
            } catch (error) {
                // Warstwa prawdy wraca do oryginału — „częściowa awaria nie
                // kasuje udanego" ma zostać prawdą co do znaku, a wymazane
                // pole i tak odtworzy się z `results` w zapisie końcowym.
                results[index] = chunks[index].send;
                failed = true;
                failure = error;

                return;
            }
        }
    };

    await Promise.all(
        Array.from({ length: Math.min(CONCURRENCY, chunks.length) }, () => worker())
    );

    // Dograj efekt do końca: przy błyskawicznej odpowiedzi zapis końcowy nie
    // może wskoczyć w środek wymazywania.
    await erased;

    clearInterval(ticker);

    // HTML sklejamy wprost (bloki niosą własne znaczniki), zwykły tekst — pustą
    // linią, czyli tym, po czym go dzieliliśmy. Przy strumieniu zapis idzie
    // ZAWSZE: pole zostało wymazane efektem, więc nawet po całkowitej awarii
    // musi się odtworzyć — wtedy z samych oryginałów w `results`.
    if (done || streaming) writeValue(target, reassemble(chunks, results, trixFor(target.id) ? '' : '\n\n'));

    if (failed) {
        // Wyczerpany limit tygodniowy to nie awaria — serwer przysyła wtedy
        // konkretny komunikat (ile było, kiedy wraca, co daje wyższy pakiet),
        // więc pokazujemy JEGO, a nie ogólne „usługa niedostępna".
        let message = done
            ? `Poprawiono ${done} z ${chunks.length} fragmentów — resztę zostawiliśmy bez zmian. Spróbuj ponownie za chwilę.`
            : 'Usługa AI jest chwilowo niedostępna. Spróbuj ponownie później.';
        // Wyczerpany limit to INFORMACJA, nie błąd — czerwony dymek sugerowałby,
        // że sprzedawca zrobił coś źle, a on po prostu wykorzystał swoją pulę.
        let variant = 'error';

        if (failure && failure.status === 429) {
            const body = await failure.json().catch(() => ({}));
            if (body.message) message = body.message;
            variant = 'info';
        } else if (failure && failure.status >= 400 && failure.status < 500 && typeof failure.json === 'function') {
            // Odmowa (403, 422) to nie awaria usługi — serwer mówi, CO jest
            // nie tak, i to trzeba pokazać. Ogólne „chwilowo niedostępna"
            // ukryło kiedyś brak sklepu na koncie administratora.
            const body = await failure.json().catch(() => ({}));
            if (body.message) message = body.message;
        }

        window.showToast(message, variant);
    }

    // Kliknięcie liczy się jako jedno użycie niezależnie od liczby fragmentów.
    used += 1;
    button.setAttribute('data-ai-uses', used);

    if (used >= max) {
        labelEl.textContent = 'Wykorzystano limit poprawek';
        button.disabled = true;
    } else {
        labelEl.textContent = originalLabel;
        button.disabled = false;
    }
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-ai-button]');
    if (button) improveWithAi(button);
});
