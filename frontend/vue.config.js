const { defineConfig } = require("@vue/cli-service");

const fs = require('fs');
const path = require('path');

// Ustalmy ścieżki absolutne do certyfikatów
const keyPath = path.join(__dirname, '../certs/localhost+1-key.pem');
const certPath = path.join(__dirname, '../certs/localhost+1.pem');
// Diagnostyka: Sprawdź czy pliki istnieją
if (!fs.existsSync(keyPath) || !fs.existsSync(certPath)) {
  console.error('\n❌ BŁĄD KONFIGURACJI SSL:');
  console.error(`Nie znaleziono certyfikatów w folderze: ${path.join(__dirname, '../certs/')}`);
  console.error('Upewnij się, że uruchomiłeś mkcert i pliki są we właściwym miejscu.\n');
  process.exit(1); // Zatrzymaj serwer, żebyś widział błąd
} else {
  console.log('\n✅ Certyfikaty SSL znalezione poprawnie.\n');
}

module.exports = defineConfig({
  transpileDependencies: true,

  devServer: {
    server: {
      type: 'https',
      options: {
        // Ścieżki do kluczy
        key: fs.readFileSync(path.join(__dirname, '../certs/localhost+1-key.pem')),
        cert: fs.readFileSync(path.join(__dirname, '../certs/localhost+1.pem')),
      },
    },
    port: 8080,
    host: '127.0.0.1',
    client: {
      overlay: true,
    }
  }
});
