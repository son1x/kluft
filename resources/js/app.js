import './bootstrap';
import Alpine from 'alpinejs';
import stammAutocomplete from './stamm-autocomplete';

window.Alpine = Alpine;
Alpine.data('stammAutocomplete', stammAutocomplete);
Alpine.start();
