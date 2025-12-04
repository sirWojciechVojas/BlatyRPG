# **🎲 BlatyRPG**

Aplikacja wspomagająca sesje RPG, zawierająca kreator postaci, cyfrowe karty postaci oraz symulator rzutów kośćmi.

## **🛠 Technologia**

Projekt jest zbudowany jako **Monorepo** z podziałem na dwa niezależne serwisy:

* **Backend:** CodeIgniter 4 (PHP) \- API RESTowe, logika gry.
* **Frontend:** Vue.js 3 (Webpack) \- Interfejs użytkownika SPA.

## **📋 Wymagania systemowe**

Aby uruchomić projekt, Twoje środowisko musi spełniać następujące wymagania:

* **PHP:** Wersja **7.4.18** (Najnowsza z rodziny PHP 7).
* **Composer:** Menedżer pakietów PHP.
* **Node.js & NPM:** Do obsługi frontendu.
* **Baza danych:** MySQL lub MariaDB.

## **🚀 Instalacja i Uruchomienie**

Aby pracować nad projektem, zaleca się otwarcie dwóch terminali: jednego dla backendu i drugiego dla frontendu.

### **1\. Klonowanie repozytorium**

git clone \[https://github.com/sirWojciechVojas/BlatyRPG.git\](https://github.com/sirWojciechVojas/BlatyRPG.git)
cd BlatyRPG

### **2\. Konfiguracja Backend (CodeIgniter 4\)**

1. Przejdź do katalogu backendu:
   cd backend

2. Zainstaluj zależności PHP:
   composer install

   *Uwaga: Composer automatycznie dobierze wersję bibliotek kompatybilną z Twoim PHP 7.4.*
3. Skonfiguruj środowisko:
   * Skopiuj plik env zmieniając jego nazwę na .env (kropka na początku jest ważna\!).
   * Otwórz plik .env w edytorze.
   * Odkomentuj (usuń \#) sekcję database i wpisz swoje dane do lokalnej bazy danych.
   * Ustaw CI\_ENVIRONMENT \= development (dla lepszego podglądu błędów).
4. Uruchom serwer developerski:
   php spark serve

   *Backend będzie dostępny pod adresem: http://localhost:8080*

### **3\. Konfiguracja Frontend (Vue 3 \+ Webpack)**

1. Przejdź do katalogu frontendu (w nowym oknie terminala):
   cd frontend

2. Zainstaluj biblioteki JavaScript:
   npm install

3. Uruchom serwer developerski:
   npm run serve

   *Frontend będzie dostępny pod adresem: http://localhost:8081 (lub podobnym – sprawdź w terminalu)*

## **📂 Struktura Projektu**

BlatyRPG/
├── backend/          \# API oparte na CodeIgniter 4
│   ├── app/          \# Kontrolery, Modele, Logika
│   ├── public/       \# Punkt wejścia (index.php)
│   └── ...
├── frontend/         \# Aplikacja Vue.js
│   ├── src/          \# Komponenty Vue, Widoki, Store (Pinia/Vuex)
│   ├── public/       \# Statyczne pliki (index.html)
│   └── vue.config.js \# Konfiguracja Webpacka
└── README.md         \# Dokumentacja główna

## **📝 Roadmapa / Status prac**

Aktualne zadania realizowane na gałęzi develop:

* \[x\] Inicjalizacja projektu (Struktura Monorepo)
* \[x\] Konfiguracja GitFlow (Main / Develop)
* \[ \] **Backend:** Podstawowa konfiguracja bazy danych
* \[ \] **Auth:** System logowania i rejestracji (JWT/Session)
* \[ \] **Frontend:** Layout główny aplikacji
* \[ \] **Feature:** Kreator Postaci (Kroki)
* \[ \] **Feature:** Mechanika rzutów kośćmi