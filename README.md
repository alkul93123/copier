# Copier
Скрипт синхронизирует тестовые бд с продакшн базами.
Например если у нас есть несколько тестовых копий, что бы они были идентичны приходится синхронизировать их. Соответственно
чем больше тестовых версий - тем больше времени отнимает их синхронизация. Т.к. некоторые пользовательские конфиги хранятся
в бд, обычная репликация master - slave нам не подойдет.

### Порядок установкий и запуска
- Склонировать репозиторий ``` git clone ```
- Создать конфиг ``` config.php ```, на основе файла ``` config.example.php ```
  - Описание параметров присутствует в ``` config.example.php ``` 
  - Файл ``` config.php ``` должен лежать в корневой директории copier
- Запустить скрипт ``` ./copier.php ```