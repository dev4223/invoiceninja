# Update

https://invoiceninja.github.io/docs/self-host-updating/

git pull origin v5-stable-hk

(composer install --no-dev -o)

php artisan ninja:post-update

php artisan migrate
php artisan optimize
php artisan queue:restart

# Restart

php artisan ninja:design-update
php artisan optimize
php artisan cache:clear
php artisan queue:restart


design?




211021
hipster.html

<<<<<<< HEAD
        <span>
            <span class="entity-property-label">$date_label:</span>
            <span class="entity-property-value">$date</span>
        </span>
        <span>
            <span class="entity-property-label">Zahlbar bis:</span>
            <span class="entity-property-value">$payment_due</span>
        </span>
        <span>
            <span class="entity-property-label">$amount_due_label:</span>
            <span class="entity-property-value" data-element="entity-details-wrapper-amount-due">$amount_due</span>
        </span>
=======