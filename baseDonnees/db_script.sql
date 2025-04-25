-- Table Etudiant
CREATE TABLE Etudiant (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    email VARCHAR(150) unique,
    mdps VARCHAR(100),
    niveau_etude VARCHAR(100)
);

-- Table Enseignant
CREATE TABLE Enseignant (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    email VARCHAR(150) unique,
    mdps VARCHAR(100),
    matiere VARCHAR(100)
);


-- Table Catégorie
CREATE TABLE Categorie (
    id_categorie INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) unique,
    description TEXT,
    id_enseignant INT,
    FOREIGN KEY (id_enseignant) REFERENCES Enseignant(id) -- Virgule supprimée ici
);

-- Table Sous-catégorie (appartient à une catégorie)
CREATE TABLE Sous_categorie (
    id_s_categorie INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) unique,
    description TEXT,
    id_categorie INT,
    id_enseignant INT,
    FOREIGN KEY (id_categorie) REFERENCES Categorie(id_categorie),
    FOREIGN KEY (id_enseignant) REFERENCES Enseignant(id)
);

--Suivre_sous_categorie
CREATE TABLE Suivre_sous_categorie(
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_s_categorie INT,
    id_enseignant INT,
    id_etudiant INT,
    FOREIGN KEY (id_enseignant) REFERENCES Enseignant(id),
    FOREIGN KEY (id_etudiant) REFERENCES Etudiant(id)

)

-- Table Publication (peut être créée par un étudiant ou un enseignant, et liée à une sous-catégorie)
CREATE TABLE Publication (
    id_pub INT PRIMARY KEY AUTO_INCREMENT,
    titre VARCHAR(255),
    date_pub DATE,
    contenu VARCHAR(255);
    note FLOAT,
    id_enseignant INT,
    id_etudiant INT,
    id_s_categorie INT,
    FOREIGN KEY (id_enseignant) REFERENCES Enseignant(id),
    FOREIGN KEY (id_etudiant) REFERENCES Etudiant(id),
    FOREIGN KEY (id_s_categorie) REFERENCES Sous_categorie(id_s_categorie)

);

-- Table Notification (envoyée à un étudiant ou enseignant)
CREATE TABLE Notification (
    id_notification INT PRIMARY KEY AUTO_INCREMENT,
    contenu TEXT,
    date_notif DATE,
    id_etudiant INT,
    id_enseignant INT,
    id_pub INT,
    FOREIGN KEY (id_etudiant) REFERENCES Etudiant(id),
    FOREIGN KEY (id_enseignant) REFERENCES Enseignant(id),
    FOREIGN KEY (id_pub) REFERENCES Publication(id_pub) -- Virgule supprimée ici
);


-- Table Commentaire (écrit par un étudiant ou enseignant sur une publication)
CREATE TABLE Commentaire (
    id INT PRIMARY KEY AUTO_INCREMENT,
    contenu TEXT,
    date_com DATE,
    id_etudiant INT,
    id_enseignant INT,
    id_pub INT,
    FOREIGN KEY (id_etudiant) REFERENCES Etudiant(id),
    FOREIGN KEY (id_enseignant) REFERENCES Enseignant(id),
    FOREIGN KEY (id_pub) REFERENCES Publication(id_pub)
);

-- Table Signalement (réalisé par un étudiant)
CREATE TABLE Signalement (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(100),
    motif TEXT,
    id_etudiant INT,
    id_pub INT,
    FOREIGN KEY (id_etudiant) REFERENCES Etudiant(id),
    FOREIGN KEY (id_pub) REFERENCES Publication(id_pub) -- Virgule supprimée ici
);