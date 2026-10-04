/**
 * Firebase: инициализация приложения и Analytics для проекта qqqwe12-d2a5d
 * Подключается после скриптов firebase-app-compat и firebase-analytics-compat
 */
(function () {
    'use strict';
    if (typeof firebase === 'undefined') return;

    var firebaseConfig = {
        apiKey: "AIzaSyCwiXvd3oWyizNIS89DSibWAXFxrPJvp-c",
        authDomain: "qqqwe12-d2a5d.firebaseapp.com",
        projectId: "qqqwe12-d2a5d",
        storageBucket: "qqqwe12-d2a5d.firebasestorage.app",
        messagingSenderId: "37296571853",
        appId: "1:37296571853:web:f3e3f8bbfdf9f4e4ea3084",
        measurementId: "G-2PL81WH77S"
    };

    var app = firebase.initializeApp(firebaseConfig);
    var analytics = null;
    try {
        analytics = firebase.analytics(app);
    } catch (e) {
        console.warn('[Firebase] Analytics:', e.message);
    }

    window.firebaseApp = app;
    window.firebaseAnalytics = analytics;
})();
