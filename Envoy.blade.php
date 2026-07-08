@servers(['web' => $server ?? 'deployer@your-server.com'])

@setup
    $repository = 'git@github.com:mahmudul-elahi/acts.git';
    $appDir = $appDir ?? '/var/www/acts';
    $branch = $branch ?? 'main';
@endsetup

@story('quick')
    pull-code
    optimize
@endstory

@story('deploy')
    update-code
    install-dependencies
    run-migrations
    build-assets
    optimize
    restart-services
@endstory

@task('pull-code', ['on' => 'web'])
    echo "Pulling latest code ({{ $branch }})..."
    cd {{ $appDir }}
    git pull origin {{ $branch }}
    php artisan queue:restart
@endtask

@task('update-code', ['on' => 'web'])
    echo "Pulling latest code ({{ $branch }})..."
    cd {{ $appDir }}
    php artisan down --retry=60 || true
    git fetch origin {{ $branch }}
    git reset --hard origin/{{ $branch }}
@endtask

@task('install-dependencies', ['on' => 'web'])
    echo "Installing composer dependencies..."
    cd {{ $appDir }}
    composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
@endtask

@task('run-migrations', ['on' => 'web'])
    echo "Running database migrations..."
    cd {{ $appDir }}
    php artisan migrate --force
@endtask

@task('build-assets', ['on' => 'web'])
    echo "Building frontend assets..."
    cd {{ $appDir }}
    npm ci
    npm run build
@endtask

@task('optimize', ['on' => 'web'])
    echo "Caching configuration, routes, and views..."
    cd {{ $appDir }}
    php artisan optimize
@endtask

@task('restart-services', ['on' => 'web'])
    echo "Restarting queues and lifting maintenance mode..."
    cd {{ $appDir }}
    php artisan queue:restart
    php artisan up
@endtask

@finished
    if ($exitCode > 0) {
        echo "Deploy failed. If the site is stuck in maintenance mode, run: php artisan up\n";
    } else {
        echo "Deploy completed successfully.\n";
    }
@endfinished
