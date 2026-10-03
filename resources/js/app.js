import './bootstrap';
import Alpine from 'alpinejs';
import stammAutocomplete from './stamm-autocomplete';
import zweiKlick from './zwei-klick';

window.Alpine = Alpine;
Alpine.data('stammAutocomplete', stammAutocomplete);
Alpine.data('zweiKlick', zweiKlick);
Alpine.start();
