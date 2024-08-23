# annas-archive-json

> [!WARNING]  
> I removed the code (html/index.php). See [CEASE_AND_DESIST.html](CEASE_AND_DESIST.html).

Translating proxy for annas-archive. It repeats the request, finds the JSON block, re-encodes it (to validate the JSON syntax), and sends the JSON to the original user agent.

## Features:

- One hour cache (no garbage collection)
- Authorization via the `Authorization` header

## Usage:

1. Create a `docker-compose.yml` file, and fill it in:
   ```yml
   version: "3"
   services:
     app:
       image: jkoop/annas-archive-json:latest
       volumes:
         - ./storage:/storage
         - ./tokens.txt:/tokens.txt
       ports:
         - 8080:8080
   ```
2. Create a `tokens.txt` file. It's a new-line separated list of authorization tokens (via `Authorization: Basic *****`). Add something unguessable.
3. Create a `storage/` folder. Make it read/writable by everyone.
4. `docker compose up -d`
5. Now you can make requests, like:
   ```sh
   curl -is -H 'Authorization: Basic SECRET' localhost:8080/isbn/9780330258647
   ```
