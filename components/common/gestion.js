 // Gestion des onglets
 const tabs = document.querySelectorAll('.tab');
 const tabContents = document.querySelectorAll('.tab-content');
 
 tabs.forEach(tab => {
     tab.addEventListener('click', () => {
         // Supprimer la classe active de tous les onglets
         tabs.forEach(t => t.classList.remove('active'));
         // Ajouter la classe active à l'onglet cliqué
         tab.classList.add('active');
         
         // Cacher tous les contenus d'onglets
         tabContents.forEach(content => content.classList.add('hidden'));
         // Afficher le contenu de l'onglet actif
         document.getElementById(tab.dataset.tab + '-tab').classList.remove('hidden');
     });
 });
 
 // Gestion des modals
 const modalCategorie = document.getElementById('modal-nouvelle-categorie');
 const btnNouvelleCategorie = document.getElementById('btn-nouvelle-categorie');
 const closeModalCategorie = document.getElementById('close-modal-categorie');
 const cancelModalCategorie = document.getElementById('cancel-modal-categorie');
 
 btnNouvelleCategorie.addEventListener('click', () => {
     modalCategorie.classList.remove('hidden');
 });
 
 closeModalCategorie.addEventListener('click', () => {
     modalCategorie.classList.add('hidden');
 });
 
 cancelModalCategorie.addEventListener('click', () => {
     modalCategorie.classList.add('hidden');
 });
 
 const modalSousCategorie = document.getElementById('modal-nouvelle-sous-categorie');
 const btnNouvelleSousCategorie = document.getElementById('btn-nouvelle-sous-categorie');
 const closeModalSousCategorie = document.getElementById('close-modal-sous-categorie');
 const cancelModalSousCategorie = document.getElementById('cancel-modal-sous-categorie');
 
 btnNouvelleSousCategorie.addEventListener('click', () => {
     modalSousCategorie.classList.remove('hidden');
 });
 
 closeModalSousCategorie.addEventListener('click', () => {
     modalSousCategorie.classList.add('hidden');
 });
 
 cancelModalSousCategorie.addEventListener('click', () => {
     modalSousCategorie.classList.add('hidden');
 });
 
 // Actions exemple (à compléter avec PHP)
 function modifierCategorie(id) {
     console.log("Modifier catégorie ID:", id);
 }
 
 function supprimerCategorie(id) {
     if (confirm('Êtes-vous sûr de vouloir supprimer cette catégorie?')) {
         // Rediriger vers supp_categorie.php avec l'ID
         window.location.href = `.../includes/supprimer/supp_categorie.php?id=${id}`;
     }
 }
 
 function modifierSousCategorie(id) {
     console.log("Modifier sous-catégorie ID:", id);
 }
 
 function supprimerSousCategorie(id) {
     if (confirm('Êtes-vous sûr de vouloir supprimer cette sous-catégorie?')) {
         // Rediriger vers supp_s_categorie.php avec l'ID
         window.location.href = `.../includes/supprimer/supp_s_categorie.php?id=${id}`;
     }
 }
 
 function modifierUtilisateur(id) {
     console.log("Modifier utilisateur ID:", id);
 }
 
 function supprimerUtilisateur(id) {
     if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur?')) {
         // Rediriger vers supp_utilisateur.php avec l'ID
         window.location.href = `.../includes/supprimer/supp_utilisateur.php?id=${id}`;
     }
 }