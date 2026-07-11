<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/functions.php';
require_once dirname(__DIR__) . '/src/layout.php';

$user = requireAuth();

pageHead('Om Veckoswisha');
pageNav($user['name']);
?>
<main class="max-w-lg mx-auto px-4 py-6 space-y-6">

  <div class="text-center">
    <span class="text-5xl">🪙</span>
    <h1 class="text-2xl font-bold text-gray-900 mt-2">Om Veckoswisha</h1>
    <p class="text-gray-500 text-sm mt-1">Hantera barnens veckopeng enkelt — och lär dem något på köpet</p>
  </div>

  <!-- Varför veckopeng? -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
    <h2 class="font-bold text-gray-900 mb-2">💡 Varför veckopeng med krav?</h2>
    <p class="text-sm text-gray-600 leading-relaxed mb-3">
      Barn som tidigt får hantera egna pengar utvecklar bättre förståelse för vad saker
      kostar, hur man sparar och hur man prioriterar. En regelbunden veckopeng med tydliga,
      rimliga förväntningar är ett av de enklaste sätten att träna det i vardagen.
    </p>
    <p class="text-sm text-gray-600 leading-relaxed mb-3">
      Forskning kring barns utveckling pekar på att <strong>tydliga rutiner och lagom stort
      eget ansvar</strong> stärker både självständighet och självkänsla. Barn vill känna sig
      kapabla — när de själva ser sambandet mellan insats och belöning växer motivationen
      inifrån, i stället för att allt hänger på tjat.
    </p>
    <p class="text-sm text-gray-600 leading-relaxed">
      Nyckeln är att reglerna är <strong>förutsägbara och rättvisa</strong>: barnet ska i
      förväg veta vad som förväntas och vad som händer om något missas. Det är precis det
      Veckoswisha hjälper till med — samma regler varje vecka, synliga för alla.
    </p>
  </div>

  <!-- Skärmtid -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
    <h2 class="font-bold text-gray-900 mb-2">📱 Skärmtid — vad säger experterna?</h2>
    <p class="text-sm text-gray-600 leading-relaxed mb-3">
      <strong>Folkhälsomyndigheten</strong> kom 2024 med Sveriges första rekommendationer
      för barns skärmanvändning på fritiden:
    </p>
    <div class="space-y-1.5 mb-3">
      <?php foreach ([
        ['0–2 år',   'Ingen skärmtid alls'],
        ['2–5 år',   'Högst 1 timme per dag'],
        ['6–12 år',  'Högst 1–2 timmar per dag'],
        ['13–18 år', 'Högst 2–3 timmar per dag'],
      ] as [$age, $rec]): ?>
      <div class="flex items-center gap-3 text-sm">
        <span class="w-20 flex-shrink-0 font-semibold text-indigo-700 bg-indigo-50 rounded-lg px-2 py-1 text-center text-xs"><?= $age ?></span>
        <span class="text-gray-700"><?= $rec ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="text-sm text-gray-600 leading-relaxed mb-3">
      Myndigheten råder också att skärmar inte används <strong>strax före läggdags</strong>
      och inte finns i sovrummet på natten — sömnen är det som påverkas allra mest.
    </p>
    <p class="text-sm text-gray-600 leading-relaxed">
      Forskningen skiljer dessutom på <strong>olika sorters skärmtid</strong>: att skapa,
      lära och spela tillsammans är inte samma sak som passivt flöde av korta klipp.
      Därför delar Veckoswisha upp skärmtiden i kategorier — 🎮 Speltid,
      📺 TV &amp; YouTube, 🤳 TikTok &amp; Reels och 🧠 Lärospel &amp; Pussel — så att
      familjens budget kan vara generös där tiden ger något, och stram där den mest rinner iväg.
    </p>
  </div>

  <!-- Funktioner -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
    <h2 class="font-bold text-gray-900 mb-3">🧭 Så funkar Veckoswisha</h2>
    <div class="space-y-4">

      <div class="flex gap-3">
        <span class="text-2xl flex-shrink-0">👶</span>
        <div>
          <p class="text-sm font-semibold text-gray-800">Barnprofiler</p>
          <p class="text-sm text-gray-500 leading-relaxed">Varje barn har sin egen sida med profilfärg, veckopeng och veckans status. Ordningen styr du i Familjeinställningar.</p>
        </div>
      </div>

      <div class="flex gap-3">
        <span class="text-2xl flex-shrink-0">✅</span>
        <div>
          <p class="text-sm font-semibold text-gray-800">Krav</p>
          <p class="text-sm text-gray-500 leading-relaxed">Familjegemensamma uppgifter som bockas av — dagliga (t.ex. bädda sängen), veckovisa (städa rummet) eller minutmål (läsa 120 min/vecka). Enskilda krav kan undantas per barn.</p>
        </div>
      </div>

      <div class="flex gap-3">
        <span class="text-2xl flex-shrink-0">⚖️</span>
        <div>
          <p class="text-sm font-semibold text-gray-800">Veckopengsregler</p>
          <p class="text-sm text-gray-500 leading-relaxed">Bestäm vad missade krav betyder: inget alls, fast avdrag per miss, procentavdrag — eller tvingande läge där alla krav måste klaras för att basen ska betalas ut. Ställs in per familj under ⚖️ Regler.</p>
        </div>
      </div>

      <div class="flex gap-3">
        <span class="text-2xl flex-shrink-0">📱</span>
        <div>
          <p class="text-sm font-semibold text-gray-800">Skärmtid som valuta</p>
          <p class="text-sm text-gray-500 leading-relaxed">Dagsbudget per kategori (🎮 📺 🤳 🧠) som räknas som veckopott. Logga med snabbknappar — löpande eller i slutet av veckan. Tid över potten kan kosta veckopeng, och bonusminuter kvittar överdrag.</p>
        </div>
      </div>

      <div class="flex gap-3">
        <span class="text-2xl flex-shrink-0">💸</span>
        <div>
          <p class="text-sm font-semibold text-gray-800">Avdrag &amp; bonusar</p>
          <p class="text-sm text-gray-500 leading-relaxed">Snabbknappar för sånt som händer ofta ("Ej dukat av tallriken −1 kr", "+30 min skärmtid för städat rum") och ett fritextfält för engångshändelser — i kronor eller skärmtidsminuter.</p>
        </div>
      </div>

      <div class="flex gap-3">
        <span class="text-2xl flex-shrink-0">📋</span>
        <div>
          <p class="text-sm font-semibold text-gray-800">Veckosammanställning</p>
          <p class="text-sm text-gray-500 leading-relaxed">I slutet av veckan låser du veckan: bas − avdrag + bonusar = slutlig veckopeng. Markera som Betald eller Skyldig, och betala direkt med Swish-knappen om barnets nummer är inlagt.</p>
        </div>
      </div>

      <div class="flex gap-3">
        <span class="text-2xl flex-shrink-0">👨‍👩‍👧</span>
        <div>
          <p class="text-sm font-semibold text-gray-800">Hela familjen</p>
          <p class="text-sm text-gray-500 leading-relaxed">Bjud in den andra föräldern så ser ni samma barn och samma regler. Barnen kan få egna konton och — om ni vill — själva bocka av krav och logga skärmtid. Ansvar från unga år, med föräldrarna som backup.</p>
        </div>
      </div>

      <div class="flex gap-3">
        <span class="text-2xl flex-shrink-0">📊</span>
        <div>
          <p class="text-sm font-semibold text-gray-800">Historik &amp; betalningslogg</p>
          <p class="text-sm text-gray-500 leading-relaxed">Veckohistorik per barn och en logg över hur mycket varje förälder betalat ut — bra när man turas om att swisha.</p>
        </div>
      </div>

      <div class="flex gap-3">
        <span class="text-2xl flex-shrink-0">💡</span>
        <div>
          <p class="text-sm font-semibold text-gray-800">Förslagslådan</p>
          <p class="text-sm text-gray-500 leading-relaxed">Saknar du något eller strular det? Skicka in ett förslag från startsidan — allt läses!</p>
        </div>
      </div>

    </div>
  </div>

  <!-- Tips -->
  <div class="bg-indigo-50 rounded-2xl border border-indigo-100 p-5">
    <h2 class="font-bold text-indigo-900 mb-2">🌟 Tips för en bra start</h2>
    <ul class="space-y-2 text-sm text-indigo-900/80">
      <li class="flex gap-2"><span>1.</span><span>Börja med <strong>få och tydliga krav</strong> — hellre tre som alltid gäller än tio som glöms bort.</span></li>
      <li class="flex gap-2"><span>2.</span><span>Låt barnet vara med och <strong>sätta reglerna</strong> — delaktighet ger motivation.</span></li>
      <li class="flex gap-2"><span>3.</span><span>Sätt skärmtidsbudgetar utifrån <strong>barnets ålder</strong> (se rekommendationerna ovan) och var generösare med skapande/lärande tid.</span></li>
      <li class="flex gap-2"><span>4.</span><span>Gör veckosammanställningen till en <strong>rutin</strong>, t.ex. söndag kväll — då blir utbetalningen något att se fram emot.</span></li>
      <li class="flex gap-2"><span>5.</span><span>Beröm insatsen, inte bara resultatet. Bonusknappen finns där av en anledning! 😉</span></li>
    </ul>
  </div>

  <p class="text-center text-xs text-gray-400">
    Källor: Folkhälsomyndighetens rekommendationer om skärmanvändning för barn och unga (2024)
    samt WHO:s riktlinjer för små barns skärmtid. Detta är allmän information, inte medicinsk rådgivning.
  </p>

</main>
<?php pageFoot(); ?>
