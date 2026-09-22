# Guía: login con Google OAuth 2.0, PDO y PHP

Proyecto independiente que muestra cómo autenticar usuarios mediante Google, guardar su perfil en MySQL con PDO y mantener una sesión PHP segura.

## Datos recuperados de Google

El ID Token verificado puede proporcionar:

- `sub`: identificador único y estable de la cuenta de Google. Es la llave correcta para identificar al usuario.
- `name`: nombre visible.
- `email`: correo electrónico.
- `email_verified`: confirma que Google verificó el correo.
- `picture`: URL de la foto de perfil (lo que a veces se denomina “pic”).
- `hd`: dominio de Google Workspace, cuando aplica.

Este ejemplo guarda `google_sub`, nombre, correo y URL de la foto. No guarda el Access Token ni el Client Secret en la base de datos.

## Requisitos

- PHP 8.2 o superior.
- Extensiones `pdo_mysql`, `json`, `curl`, `openssl` y `mbstring`.
- MySQL/MariaDB.
- Composer.
- HTTPS en producción. Google permite HTTP únicamente para `localhost`.

## Estructura

```text
ejemplo-google-login/
├── public/                  # Único directorio que debería exponerse por web
│   ├── index.php
│   ├── login.php
│   ├── google-start.php
│   ├── google-callback.php
│   ├── dashboard.php
│   ├── logout.php
│   └── assets/style.css
├── src/
│   ├── bootstrap.php        # Entorno, sesión y headers
│   ├── Database.php         # Conexión PDO
│   ├── GoogleAuth.php       # Cliente OAuth
│   └── Auth.php             # Sesión del usuario
├── sql/schema.sql
├── .env.example
├── composer.json
└── README.md
```

## 1. Instalar dependencias

Desde esta carpeta:

```bash
composer install
```

En Hostinger, si el PHP predeterminado de la terminal es 8.1:

```bash
/opt/alt/php83/usr/bin/php "$(which composer)" install --no-dev --optimize-autoloader
```

## 2. Crear la base de datos

Ejecuta `sql/schema.sql` desde phpMyAdmin o MySQL. Si utilizarás otro nombre de base, modifica el `CREATE DATABASE`, el `USE` y posteriormente `DB_NAME`.

La tabla utiliza dos restricciones únicas:

- `google_sub`: evita duplicar una identidad de Google.
- `email`: evita asociar un correo a dos identidades diferentes.

## 3. Configurar variables de entorno

Copia `.env.example` como `.env`:

```bash
cp .env.example .env
```

En Windows también puedes copiarlo manualmente. Completa la conexión MySQL y las credenciales de Google. `.env` está excluido de Git y nunca debe enviarse al repositorio.

Ejemplo local:

```dotenv
APP_ENV=local
APP_URL=http://localhost/servicioSocial/ejemplo-google-login/public
SESSION_NAME=google_login_example

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=google_login_example
DB_USER=root
DB_PASSWORD=

GOOGLE_CLIENT_ID=000000000000-xxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=tu_secreto
GOOGLE_REDIRECT_URI=http://localhost/servicioSocial/ejemplo-google-login/public/google-callback.php
GOOGLE_ALLOWED_DOMAIN=
```

Si defines `GOOGLE_ALLOWED_DOMAIN`, el callback verificará el claim `hd`; esto es más seguro que revisar solamente el texto después de `@` en el correo.

## 4. Crear el cliente en Google Cloud

1. Abre Google Auth Platform/Google Cloud Console.
2. Configura la pantalla de consentimiento.
3. Si la aplicación está en modo de prueba, agrega las cuentas que usarás como usuarios de prueba.
4. Crea un cliente OAuth de tipo **Aplicación web**.
5. Agrega la URI de redirección autorizada local:

   ```text
   http://localhost/servicioSocial/ejemplo-google-login/public/google-callback.php
   ```

6. Para producción registra además la URL HTTPS real y coloca exactamente el mismo valor en `GOOGLE_REDIRECT_URI`.

El esquema, dominio, ruta, mayúsculas y barra final deben coincidir exactamente. Este flujo de servidor no necesita un origen JavaScript autorizado.

## 5. Abrir el ejemplo

Con Apache/XAMPP:

```text
http://localhost/servicioSocial/ejemplo-google-login/public/login.php
```

La primera autenticación crea al usuario. Los siguientes accesos actualizan nombre, correo, foto y `last_login_at` usando consultas preparadas de PDO.

## Flujo de autenticación

```text
login.php
   ↓
google-start.php ── state + PKCE ──→ Google
   ↓                                  ↓
google-callback.php ← código OAuth ───┘
   ↓ verifica state, token, email y dominio
MySQL (INSERT o UPDATE con PDO)
   ↓
dashboard.php
```

### Por qué se usa `sub`

Un correo puede cambiar; el claim `sub` es el identificador estable entregado por Google para el usuario y el cliente. Por eso la búsqueda de cuentas existentes se realiza mediante `google_sub`, no mediante el correo.

### State y PKCE

- `state` relaciona la respuesta de Google con la sesión que inició el proceso y mitiga CSRF.
- PKCE crea un `code_verifier` temporal y hace que un código interceptado no sea suficiente para intercambiar tokens.
- Ambos valores expiran en diez minutos y se eliminan de la sesión al entrar al callback.

## Buenas prácticas incluidas

- Consultas PDO preparadas y emulación deshabilitada.
- `utf8mb4` y excepciones de PDO.
- Verificación criptográfica del ID Token mediante la librería oficial.
- Comprobación de `email_verified`.
- Restricción opcional por el claim `hd` de Workspace.
- Client Secret únicamente en `.env`.
- Regeneración del ID de sesión después de autenticar.
- Cookies `HttpOnly`, `SameSite=Lax` y `Secure` bajo HTTPS.
- `session.use_strict_mode` y sesiones basadas solo en cookies.
- Escape HTML de todos los datos mostrados.
- Cierre de sesión por POST con token CSRF.
- Páginas privadas con `Cache-Control: no-store`.
- Errores técnicos enviados al log, no al navegador.
- No se almacenan Access Tokens ni Refresh Tokens porque el ejemplo solo necesita identidad.

## Producción

- Configura el Document Root en `ejemplo-google-login/public` cuando el hosting lo permita.
- Usa HTTPS y una URI de callback HTTPS.
- Coloca `.env` fuera del directorio público o bloquéalo en el servidor.
- Desactiva `display_errors` y registra los errores en un archivo protegido.
- Rota inmediatamente cualquier Client Secret o contraseña expuesta.
- Configura respaldos de MySQL.
- Considera una lista previa de usuarios autorizados si no deseas que cualquier cuenta de Google pueda registrarse.
- Define una política de privacidad: nombre, correo y foto son datos personales.

## Problemas frecuentes

### `redirect_uri_mismatch`

La URI enviada no coincide exactamente con la registrada en Google Cloud. Compara `GOOGLE_REDIRECT_URI` carácter por carácter.

### `Class Google\\Client not found`

Ejecuta `composer install` y verifica `vendor/autoload.php`.

### Error de conexión PDO

Comprueba `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` y que se haya importado `schema.sql`.

### La sesión se pierde después de Google

Comprueba que el inicio y el callback usen el mismo dominio y esquema. No alternes entre `localhost` y `127.0.0.1`, ni entre HTTP y HTTPS.

### Correo duplicado

La base detectó el mismo correo relacionado con otro `google_sub`. No unas cuentas automáticamente por correo; revisa el caso manualmente antes de modificar datos.

## Alcance educativo

El ejemplo crea usuarios automáticamente después de una autenticación válida. En sistemas internos suele ser preferible una tabla de invitaciones o una lista de correos autorizados antes de permitir la creación. Esa decisión depende de los requisitos del proyecto, no solamente de OAuth.
