import './bootstrap';
import Alpine from 'alpinejs';
import stammAutocomplete from './stamm-autocomplete';
import zweiKlick from './zwei-klick';
import shopBestellung from './shop-bestellung';
import umfrage from './umfrage';

window.Alpine = Alpine;
Alpine.data('stammAutocomplete', stammAutocomplete);
Alpine.data('zweiKlick', zweiKlick);
Alpine.data('shopBestellung', shopBestellung);
Alpine.data('umfrage', umfrage);
Alpine.start();
