# SiVote Engine

SiVote is a internet voting that supports **secret ballots** and is intended **for private organizations**. System is comprised of at least two modules - you'll also need the [SiVote Sender](https://github.com/Institut-IP21/SiVoteSender).

SiVote Engine contains most of functionality including ballot creation, display, voting code generation, and result collection. 

## Hosted version

You can use the hosted version at [eGlasovanje.si](https://eglasovanje.si/) that's free for smaller organizations. 

## Installation

You can use the included Docker compose image or deploy directly (see image for requirements).

```bash
    cp .env.example .env
    docker-compose up -d
    docker-compose exec evote_app bash
    composer install
    php artisan migrate
    yarn install
    yarn dev
```
    
## Learn more

We've published a number of articles explaning all the aspects of the model and system on the [eGlasovanje.si website](https://eglasovanje.si/vsi-clanki)

## Feedback & Support

If you have any feedback or need support, please reach out to us at info@ip21.si

