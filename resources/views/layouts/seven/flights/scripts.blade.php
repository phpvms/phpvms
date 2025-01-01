@section('scripts')
    @if (setting('bids.block_aircraft', false))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const bidModal = new bootstrap.Modal(document.getElementById('bidModal'));
                let aircrafts = [{
                    id: 0,
                    text: 'Loading Aircrafts...'
                }];
                let sel = document.getElementById('aircraft_select');

                document.querySelectorAll("button.save_flight").forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();

                        const btn = e.target;
                        const class_name = btn.getAttribute(
                            'x-saved-class'); // classname to use is set on the element
                        const flight_id = btn.getAttribute('x-id');
                        sel.setAttribute('x-saved-class', class_name);
                        sel.setAttribute('x-id', flight_id);

                        if (!btn.classList.contains(class_name)) {
                            const bidModal = new bootstrap.Modal(document.getElementById('bidModal'))

                            bidModal.show();

                            fetch(`{{ Config::get('app.url') }}/api/flights/${flight_id}/aircraft`, {
                                    headers: {
                                        'X-API-KEY': document.querySelector('meta[name="api-key"]')
                                            .getAttribute('content')
                                    }
                                })
                                .then(response => response.json())
                                .then(data => {
                                    aircrafts = [];
                                    data.forEach(ac => {
                                        const text =
                                            `[${ac.icao}] ${ac.registration} ${ac.registration !== ac.name ? ` ${ac.name}` : ''}`;
                                        aircrafts.push({
                                            id: ac.id,
                                            text: text
                                        });
                                    });
                                    sel.innerHTML = '';
                                    // Initialize Tom Select only if it hasn't been initialized yet
                                    const selectElement = document.getElementById(
                                        'aircraft_select');

                                    if (!selectElement.tomselect) {
                                        new TomSelect(selectElement, {
                                            options: aircrafts,
                                            valueField: 'id',
                                            labelField: 'text',
                                            searchField: 'text'
                                        });
                                    } else {
                                        // Update the existing Tom Select instance with new options
                                        selectElement.tomselect.clearOptions();
                                        selectElement.tomselect.addOptions(aircrafts);
                                    }
                                });
                        } else {
                            phpvms.bids.removeBid(flight_id).then(() => {
                                console.log('successfully removed flight');
                                btn.classList.remove(class_name);
                                location.reload();
                            }).catch((error) => {
                                if (error.response && error.response.data)
                                    alert(`Error removing bid: ${error.response.data.details}`);
                                else alert(`Error removing bid: ${error.message}`);
                            });
                        }
                    });
                });

                document.getElementById('with_aircraft').addEventListener('click', () => {
                    const ac_id = sel.value;
                    const flight_id = sel.getAttribute('x-id');
                    const class_name = sel.getAttribute('x-saved-class');
                    phpvms.bids.addBid(flight_id, ac_id).then(() => {
                        document.querySelector(`button.save_flight[x-id="${flight_id}"]`).classList.add(
                            class_name);
                        location.reload();
                    }).catch((error) => {
                        if (error.response && error.response.data)
                            alert(`Error adding bid: ${error.response.data.details}`);
                        else alert(`Error adding bid: ${error.message}`);
                    });
                });

                document.getElementById('without_aircraft').addEventListener('click', () => {
                    const flight_id = sel.getAttribute('x-id');
                    const class_name = sel.getAttribute('x-saved-class');
                    phpvms.bids.addBid(flight_id).then(() => {
                        document.querySelector(`button.save_flight[x-id="${flight_id}"]`).classList.add(
                            class_name);
                        location.reload();
                    }).catch((error) => {
                        if (error.response && error.response.data)
                            alert(`Error adding bid: ${error.response.data.details}`);
                        else alert(`Error adding bid: ${error.message}`);
                    });
                });
            });
        </script>
    @else
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll("button.save_flight").forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();

                        const btn = e.target;
                        const class_name = btn.getAttribute(
                            'x-saved-class'); // classname to use is set on the element
                        const flight_id = btn.getAttribute('x-id');

                        if (!btn.classList.contains(class_name)) {
                            phpvms.bids.addBid(flight_id).then(() => {
                                btn.classList.add(class_name);
                                location.reload();

                            }).catch((error) => {
                                if (error.response && error.response.data)
                                    alert(`Error adding bid: ${error.response.data.details}`);
                                else alert(`Error adding bid: ${error.message}`);
                            });
                        } else {
                            phpvms.bids.removeBid(flight_id).then(() => {
                                btn.classList.remove(class_name);
                                location.reload();
                            }).catch((error) => {
                                if (error.response && error.response.data)
                                    alert(`Error removing bid: ${error.response.data.details}`);
                                else alert(`Error removing bid: ${error.message}`);
                            });
                        }
                    });
                });
            });
        </script>
    @endif
    @include('scripts.airport_search')
@endsection
