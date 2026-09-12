/**
 * Helmetsan Store & Dealer Locator Map Engine
 * Powered by LeafletJS - Lightweight & SEO friendly
 */
document.addEventListener('DOMContentLoaded', () => {
    const mapContainer = document.getElementById('hs-store-locator-map');
    if (!mapContainer || typeof L === 'undefined' || !window.hsDealers) {
        return;
    }

    // Default center of map - fall back to first dealer or center of Europe/Global
    let defaultLat = 50.1109; // Frankfurt/Europe roughly center
    let defaultLng = 8.6821;
    let defaultZoom = 5;

    if (window.hsDealers.length > 0) {
        defaultLat = window.hsDealers[0].lat;
        defaultLng = window.hsDealers[0].lng;
    }

    // Initialize map
    const map = L.map('hs-store-locator-map', {
        scrollWheelZoom: false
    }).setView([defaultLat, defaultLng], defaultZoom);

    // Tile Basemaps
    const lightLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });

    const darkLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>'
    });

    // Default to Light Layer
    lightLayer.addTo(map);

    // Map Style Switcher
    const themeButtons = document.querySelectorAll('.hs-locator-theme-btn');
    themeButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            themeButtons.forEach(b => b.classList.remove('hs-locator-theme-btn--active'));
            this.classList.add('hs-locator-theme-btn--active');
            
            const theme = this.getAttribute('data-theme');
            if (theme === 'dark') {
                map.removeLayer(lightLayer);
                darkLayer.addTo(map);
            } else {
                map.removeLayer(darkLayer);
                lightLayer.addTo(map);
            }
        });
    });

    // Custom SVG Pin Creator
    const createCustomIcon = (logoUrl) => {
        return L.divIcon({
            className: 'hs-custom-marker-icon',
            html: `
                <div class="hs-map-pin">
                    <div class="hs-map-pin__wrapper">
                        ${logoUrl ? `<img class="hs-map-pin__logo" src="${logoUrl}" alt="logo" />` : '<span class="hs-map-pin__dot"></span>'}
                    </div>
                    <div class="hs-map-pin__triangle"></div>
                </div>
            `,
            iconSize: [40, 48],
            iconAnchor: [20, 48],
            popupAnchor: [0, -48]
        });
    };

    const markersGroup = L.layerGroup().addTo(map);
    let activeDealers = [...window.hsDealers];

    // Function to render markers and list pane side-by-side
    const renderDealers = (dealersToRender) => {
        markersGroup.clearLayers();
        const listContainer = document.getElementById('hs-locator-store-list');
        
        if (listContainer) {
            listContainer.innerHTML = '';
        }

        if (dealersToRender.length === 0) {
            if (listContainer) {
                listContainer.innerHTML = '<p class="hs-locator-no-results">No physical dealers match the active filters.</p>';
            }
            return;
        }

        const bounds = [];

        dealersToRender.forEach((dealer, index) => {
            const latLng = [dealer.lat, dealer.lng];
            bounds.push(latLng);

            // Create Marker
            const markerIcon = createCustomIcon(dealer.logo);
            const marker = L.marker(latLng, { icon: markerIcon }).addTo(markersGroup);

            // Bind Popup
            const popupContent = `
                <div class="hs-map-popup">
                    <h4 class="hs-map-popup__title">${dealer.title}</h4>
                    <p class="hs-map-popup__address">${dealer.address}</p>
                    ${dealer.phone ? `<a href="tel:${dealer.phone}" class="hs-map-popup__link hs-map-popup__link--phone">📞 ${dealer.phone}</a>` : ''}
                    <a href="${dealer.link}" class="hs-map-popup__cta">View Store Profile &rarr;</a>
                </div>
            `;
            marker.bindPopup(popupContent);

            // Render Card in Sidebar
            if (listContainer) {
                const card = document.createElement('article');
                card.className = 'hs-panel hs-locator-card';
                card.innerHTML = `
                    <div class="hs-locator-card__main">
                        ${dealer.logo ? `
                            <div class="hs-locator-card__logo-wrap">
                                <img src="${dealer.logo}" alt="${dealer.title} Logo" loading="lazy" />
                            </div>
                        ` : ''}
                        <div class="hs-locator-card__content">
                            <h3>${dealer.title}</h3>
                            <p class="hs-locator-card__address">${dealer.address}</p>
                            ${dealer.phone ? `<a href="tel:${dealer.phone}" class="hs-locator-card__contact">📞 ${dealer.phone}</a>` : ''}
                            <div class="hs-locator-card__brands-grid">
                                ${dealer.brands.slice(0, 4).map(b => `<span class="hs-badge hs-badge--sm">${b}</span>`).join('')}
                                ${dealer.brands.length > 4 ? `<span class="hs-badge hs-badge--sm hs-badge--ghost">+${dealer.brands.length - 4} more</span>` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="hs-locator-card__footer">
                        <a href="${dealer.link}" class="hs-btn hs-btn--sm hs-btn--ghost">Store Profile</a>
                        <button type="button" class="hs-btn hs-btn--sm hs-btn--primary hs-locator-card__focus-btn" data-index="${index}">Focus Map</button>
                    </div>
                `;

                // Handle clicking "Focus Map" button on card
                card.querySelector('.hs-locator-card__focus-btn').addEventListener('click', (e) => {
                    e.stopPropagation();
                    map.setView(latLng, 14);
                    marker.openPopup();
                    
                    // Smooth scroll card to view or highlight it
                    document.querySelectorAll('.hs-locator-card').forEach(c => c.classList.remove('hs-locator-card--active'));
                    card.classList.add('hs-locator-card--active');
                });

                listContainer.appendChild(card);
            }
        });

        // Fit map bounds to show all markers beautifully
        if (bounds.length > 0 && dealersToRender.length > 1) {
            map.fitBounds(bounds, { padding: [50, 50] });
        } else if (bounds.length === 1) {
            map.setView(bounds[0], 12);
        }
    };

    // Filter Logic
    const searchInput = document.getElementById('hs-locator-search');
    const brandSelect = document.getElementById('hs-locator-brand');
    const typeSelect = document.getElementById('hs-locator-type');
    const geoBtn = document.getElementById('hs-locator-geolocation-btn');

    const filterDealers = () => {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const selectedBrand = brandSelect ? brandSelect.value : '';
        const selectedType = typeSelect ? typeSelect.value : '';

        activeDealers = window.hsDealers.filter(dealer => {
            const matchQuery = query === '' || 
                               dealer.title.toLowerCase().includes(query) || 
                               dealer.address.toLowerCase().includes(query);
            
            const matchBrand = selectedBrand === '' || 
                               dealer.brands.map(b => b.toLowerCase()).includes(selectedBrand.toLowerCase());

            const matchType = selectedType === '' || 
                             (selectedType === 'online' && dealer.online_store) ||
                             (selectedType === 'physical' && dealer.offline_store);

            return matchQuery && matchBrand && matchType;
        });

        renderDealers(activeDealers);
    };

    if (searchInput) searchInput.addEventListener('input', filterDealers);
    if (brandSelect) brandSelect.addEventListener('change', filterDealers);
    if (typeSelect) typeSelect.addEventListener('change', filterDealers);

    // HTML5 Geolocation support
    if (geoBtn) {
        geoBtn.addEventListener('click', () => {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser.');
                return;
            }

            geoBtn.disabled = true;
            geoBtn.textContent = 'Locating...';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    geoBtn.disabled = false;
                    geoBtn.textContent = '📍 Near Me';
                    
                    const userLat = position.coords.latitude;
                    const userLng = position.coords.longitude;

                    // Draw a user marker
                    L.circle([userLat, userLng], {
                        color: 'var(--hs-primary)',
                        fillColor: 'var(--hs-primary)',
                        fillOpacity: 0.2,
                        radius: 5000 // 5km
                    }).addTo(map);

                    // Sort active dealers by distance
                    const getDistance = (lat1, lon1, lat2, lon2) => {
                        const R = 6371; // km
                        const dLat = (lat2 - lat1) * Math.PI / 180;
                        const dLon = (lon2 - lon1) * Math.PI / 180;
                        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                                  Math.sin(dLon / 2) * Math.sin(dLon / 2);
                        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                        return R * c;
                    };

                    activeDealers.sort((a, b) => {
                        const distA = getDistance(userLat, userLng, a.lat, a.lng);
                        const distB = getDistance(userLat, userLng, b.lat, b.lng);
                        return distA - distB;
                    });

                    renderDealers(activeDealers);
                    map.setView([userLat, userLng], 10);
                },
                () => {
                    geoBtn.disabled = false;
                    geoBtn.textContent = '📍 Near Me';
                    alert('Unable to retrieve your location.');
                }
            );
        });
    }

    // Initial render
    renderDealers(activeDealers);
});
