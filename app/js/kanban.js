// kanban.js – Kanban nástenka: zobrazenie, pridanie, presúvanie, prideľovanie a mazanie úloh
//
// Hlavné pravidlo: pravdivé sú dáta v poli "ulohy", obrazovka je len ich obrázok.
// Každá zmena = zmeň pole → ulož → (ak treba) vykresli nanovo.


// ===== DÁTA =====

// Tri stĺpce nástenky. Každá hodnota je zároveň status úlohy aj id stĺpca v HTML.
const STAVY = ["todo", "inprogress", "done"];

// Všetky úlohy – pri štarte sa načítajú z localStorage
const ulohy = nacitaj("ulohy");

// Úloha, pri ktorej používateľ klikol na × – zmaže sa až po potvrdení v modáli
let idNaZmazanie = null;


// ===== PRVKY ZO STRÁNKY =====

const formUloha = document.getElementById("formUloha");
const nazovUlohy = document.getElementById("nazovUlohy");
const popisUlohy = document.getElementById("popisUlohy");
const prioritaUlohy = document.getElementById("prioritaUlohy");
const modalZmazat = document.getElementById("modalZmazat");

// ===== POMOCNÉ FUNKCIE =====

// Nájde úlohu podľa id
function najdiUlohu(id) {
  return ulohy.find(function (uloha) { return uloha.id === id; });
}

// Zmaže úlohu podľa id – splice mení pole na mieste, lebo ulohy je const
// TODO STEP-47: sem pribudne fetch na server (DELETE)
function zmazUlohu(id) {
  const index = ulohy.findIndex(function (uloha) { return uloha.id === id; });
  if (index === -1) return;   // splice(-1, 1) by inak zmazal poslednú úlohu

  ulohy.splice(index, 1);
  uloz("ulohy", ulohy);
  vykresliNastenku();
}

// Vytvorí jednu možnosť <option> do rozbaľovacieho zoznamu
function vytvorMoznost(hodnota, text) {
  const moznost = document.createElement("option");
  moznost.value = hodnota;      // čo sa uloží (prehliadač z toho vždy spraví text)
  moznost.textContent = text;   // čo vidí používateľ
  return moznost;
}

// Vytvorí rozbaľovací zoznam členov tímu, cez ktorý sa úloha prideľuje
function vytvorVyberClena(uloha) {
  const vyber = document.createElement("select");
  vyber.appendChild(vytvorMoznost("", "– nepridelené –"));
  for (const clen of aktualnyTim.members) {
    vyber.appendChild(vytvorMoznost(clen.id, clen.name));
  }

  // ak úloha už niekomu patrí, zoznam ukáže jeho meno
  if (uloha.assigned_to !== null) {
    vyber.value = uloha.assigned_to;
  }

  // po výbere iného člena zapíš zmenu do úlohy
  vyber.addEventListener("change", function () {
    if (vyber.value === "") {
      uloha.assigned_to = null;                  // "nepridelené"
    } else {
      uloha.assigned_to = Number(vyber.value);   // value je text "2" → číslo 2
    }
    uloz("ulohy", ulohy);
  });

  return vyber;
}

// Vytvorí tlačidlo ×, ktoré otvorí potvrdenie zmazania úlohy
function vytvorTlacidloZmazat(uloha) {
  const tlacidlo = document.createElement("button");
  tlacidlo.type = "button";
  tlacidlo.textContent = "×";
  tlacidlo.setAttribute("aria-label", "Zmazať kartu");   // čítačka by "×" prečítala ako "krát"

  tlacidlo.addEventListener("click", function () {
    idNaZmazanie = uloha.id;
    modalZmazat.returnValue = "";   // Esc ho nemení – ostala by v ňom odpoveď z minulého otvorenia
    modalZmazat.showModal();
  });

  return tlacidlo;
}

// Vytvorí kartu jednej úlohy: názov, farba podľa priority, výber člena a tlačidlo ×
function vytvorKartu(uloha) {
  const karta = document.createElement("div");
  karta.classList.add("karta", uloha.priority);   // .low / .medium / .high = farebný okraj
  karta.dataset.id = uloha.id;                    // podľa toho pri presune zistíme, o ktorú úlohu ide
  karta.textContent = uloha.title;
  karta.appendChild(vytvorVyberClena(uloha));
  karta.appendChild(vytvorTlacidloZmazat(uloha));
  return karta;
}


// ===== VYKRESLENIE =====

// Vymaže stĺpce a nakreslí do nich všetky úlohy aktuálneho tímu
function vykresliNastenku() {
  for (const stav of STAVY) {
    document.getElementById(stav).innerHTML = "";
  }

  for (const uloha of ulohy) {
    if (uloha.team_id !== aktualnyTim.id) continue;   // úlohy iných tímov preskoč

    // id stĺpca = status úlohy, takže stĺpec nájdeme priamo
    document.getElementById(uloha.status).appendChild(vytvorKartu(uloha));
  }
}


// ===== UDALOSTI =====

// Nová úloha z formulára – vždy začína v To Do
formUloha.addEventListener("submit", function (e) {
  e.preventDefault();   // formulár by inak obnovil stránku

  const nazov = nazovUlohy.value.trim();   // trim = bez medzier na začiatku a na konci
  if (nazov === "") return;                // názov len z medzier nepridáme

  // formát = stĺpce tabuľky tasks v databáze
  const novaUloha = {
    id: Date.now(),                        // čas v ms → vždy iné číslo
    title: nazov,
    description: popisUlohy.value.trim(),
    status: "todo",
    priority: prioritaUlohy.value,
    assigned_to: null,                     // nikto, kým ju niekto nepridelí
    team_id: aktualnyTim.id
  };

  ulohy.push(novaUloha);
  uloz("ulohy", ulohy);
  vykresliNastenku();
  formUloha.reset();
});

// Zatvorenie modálu (tlačidlo aj Esc) – zmažeme len pri potvrdení "Zmazať"
modalZmazat.addEventListener("close", function () {
  if (modalZmazat.returnValue === "zmazat") {
    zmazUlohu(idNaZmazanie);
  }
  idNaZmazanie = null;
});

// Zapne presúvanie myšou (knižnica SortableJS) pre jeden stĺpec
function zapniPresuvanie(stav) {
  new Sortable(document.getElementById(stav), {
    group: "nastenka",        // spoločná skupina → karty sa dajú presúvať medzi stĺpcami
    animation: 150,           // plynulý pohyb karty (ms)
    filter: "select, button", // ťahanie nezačne na výbere člena ani na tlačidle ×...
    preventOnFilter: false,   // ...a výber aj tlačidlo normálne fungujú

    // knižnica zavolá túto funkciu sama, keď používateľ pustí kartu
    onEnd: function (evt) {
      if (evt.from === evt.to) return;   // pustil ju v tom istom stĺpci → status sa nemení

      const uloha = najdiUlohu(Number(evt.item.dataset.id));   // data-id je text → číslo
      uloha.status = evt.to.id;                                 // id stĺpca = nový status
      uloz("ulohy", ulohy);
    }
  });
}


// ===== ŠTART =====

vykresliNastenku();
for (const stav of STAVY) {
  zapniPresuvanie(stav);
}
