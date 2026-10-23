// data.js – ukladanie a načítanie dát (zatiaľ localStorage, neskôr databáza)
//
// Formát dát = stĺpce v databáze kolegyne, aby sa dalo napojiť bez prekladania:
//   úloha: { id, title, description, status, priority, assigned_to, team_id }
//   status:      "todo" | "inprogress" | "done"
//   priority:    "low" | "medium" | "high"
//   assigned_to: id používateľa, alebo null, ak úloha nie je pridelená


// Načíta pole z localStorage podľa kľúča (napr. "ulohy").
// Ak tam ešte nič nie je, vráti prázdne pole.
function nacitaj(kluc) {
  const text = localStorage.getItem(kluc);
  if (text === null) {
    return [];
  }
  return JSON.parse(text);   // text → pole
}

// Uloží pole do localStorage ako text (JSON)
function uloz(kluc, data) {
  localStorage.setItem(kluc, JSON.stringify(data));
}


// Tím, s ktorým nástenka pracuje – členovia sú používatelia (id + meno)
// shortcut: tím je natvrdo; nahradiť tímom prihláseného používateľa, keď bude login a obrazovka učiteľa
const aktualnyTim = {
  id: 1,
  name: "STEP",
  members: [
    { id: 1, name: "Dorota" },
    { id: 2, name: "Dagmar" },
    { id: 3, name: "Marianna" },
    { id: 4, name: "Matus" }
  ]
};
