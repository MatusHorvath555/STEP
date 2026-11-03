// kanban.js – presúvanie kariet na nástenke (knižnica SortableJS)
// Karty kreslí kanban.php. Tu sa po presune len pošle nový stav na server (zmen_stav.php).

for (const zoznam of document.querySelectorAll(".zoznam")) {
  new Sortable(zoznam, {
    group: "nastenka",   // spoločná skupina → karty sa dajú presúvať medzi stĺpcami
    animation: 150,      // plynulý pohyb karty (ms)

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
