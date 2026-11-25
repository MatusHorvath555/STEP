// kanban.js – presúvanie, úprava a mazanie kariet na nástenke
// Karty kreslí kanban.php. Tu sa zmeny len pošlú na server cez fetch:
//   presun → zmen_stav.php, úprava → uprav_ulohu.php, zmazanie → zmaz_ulohu.php

// ===== Presúvanie kariet (knižnica SortableJS) =====
for (const zoznam of document.querySelectorAll(".zoznam")) {
  new Sortable(zoznam, {
    group: "nastenka",        // spoločná skupina → karty sa dajú presúvať medzi stĺpcami
    animation: 150,           // plynulý pohyb karty (ms)
    filter: ".karta-akcie",   // ťahanie nezačne na tlačidlách Upraviť / Zmazať...
    preventOnFilter: false,   // ...a kliknutie na ne normálne funguje

    // knižnica zavolá túto funkciu sama, keď používateľ pustí kartu
    onEnd: function (evt) {
      if (evt.from === evt.to) return;   // ostala v tom istom stĺpci → stav sa nemení

      fetch("zmen_stav.php", {
        method: "POST",
        body: new URLSearchParams({
          id: evt.item.dataset.id,         // data-id na karte
          status: evt.to.dataset.status,   // data-status na stĺpci, kam ju pustil
          csrf_token: CSRF
        })
      })
        .then(function (odpoved) {
          if (!odpoved.ok) throw new Error(odpoved.status);
        })
        .catch(function () {
          alert("Zmenu sa nepodarilo uložiť. Úlohu môže presunúť len ten, komu je pridelená, alebo vedúci tímu.");
          location.reload();   // nástenka znova ukáže skutočný stav z databázy
        });
    }
  });
}


// ===== Úprava a mazanie kariet (len vedúci tímu) =====
// Okná a tlačidlá vykreslí kanban.php iba vedúcemu – ostatní ich na stránke nemajú.
const dialogUprav = document.getElementById("dialogUprav");
const dialogZmaz = document.getElementById("dialogZmaz");

if (dialogUprav && dialogZmaz) {
  const formUprav = document.getElementById("formUprav");
  const formZmaz = document.getElementById("formZmaz");

  // Klik na tlačidlo na karte otvorí okno vyplnené údajmi karty (z jej data-* atribútov)
  document.addEventListener("click", function (e) {
    const karta = e.target.closest(".karta");
    if (!karta) return;

    if (e.target.closest(".karta-uprav")) {
      formUprav.elements.id.value = karta.dataset.id;
      formUprav.elements.title.value = karta.dataset.title;
      formUprav.elements.description.value = karta.dataset.description;
      formUprav.elements.assigned_to.value = karta.dataset.assigned;
      formUprav.elements.deadline.value = karta.dataset.deadline;
      formUprav.elements.priority.value = karta.dataset.priority;
      otvorOkno(dialogUprav);
    }

    if (e.target.closest(".karta-zmaz")) {
      formZmaz.elements.id.value = karta.dataset.id;
      dialogZmaz.querySelector(".modal-nazov").textContent = karta.dataset.title;
      otvorOkno(dialogZmaz);
    }
  });

  formUprav.addEventListener("submit", function (e) {
    e.preventDefault();   // formulár by inak obnovil stránku bez uloženia
    posliFormular("uprav_ulohu.php", formUprav);
  });

  formZmaz.addEventListener("submit", function (e) {
    e.preventDefault();
    posliFormular("zmaz_ulohu.php", formZmaz);
  });

  // Tlačidlá "Zrušiť" zavrú svoje okno
  for (const tlacidlo of document.querySelectorAll("[data-zavriet]")) {
    tlacidlo.addEventListener("click", function () {
      tlacidlo.closest("dialog").close();
    });
  }
}

// Otvorí okno so zmazanou starou chybovou hláškou
function otvorOkno(dialog) {
  dialog.querySelector(".modal-chyba").textContent = "";
  dialog.querySelector("[type=submit]").disabled = false;
  dialog.showModal();
}

// Pošle formulár na server. Pri úspechu obnoví stránku – karty a súčty potom nakreslí
// kanban.php priamo z databázy. Pri chybe ukáže hlášku zo servera priamo v okne.
function posliFormular(adresa, formular) {
  const data = new FormData(formular);
  data.append("csrf_token", CSRF);

  const tlacidlo = formular.querySelector("[type=submit]");
  tlacidlo.disabled = true;   // aby sa pri dvojkliku neposlalo dvakrát

  fetch(adresa, { method: "POST", body: data })
    .then(function (odpoved) {
      return odpoved.json()
        .catch(function () { return {}; })   // server neposlal JSON (napr. chyba v PHP)
        .then(function (json) {
          if (!odpoved.ok) throw new Error(json.chyba || "Zmenu sa nepodarilo uložiť.");
        });
    })
    .then(function () {
      location.reload();
    })
    .catch(function (chyba) {
      formular.querySelector(".modal-chyba").textContent = chyba.message;
      tlacidlo.disabled = false;
    });
}
