@section('scripts')
    @if (setting('bids.block_aircraft', false))
        <script>
            $(document).ready(function() {
                let aircrafts = [{
                    id: 0,
                    text: 'Loading Aircrafts...'
                }];
                let sel = $('#aircraft_select');

                $("button.save_flight").click(function(e) {
                    e.preventDefault();

                    const btn = $(this);
                    const class_name = btn.attr('x-saved-class'); // classname to use is set on the element
                    const flight_id = btn.attr('x-id');
                    $('#aircraft_select').attr('x-saved-class', class_name)
                    $('#aircraft_select').attr('x-id', flight_id)

                    if (!btn.hasClass(class_name)) {
                        $('#bidModal').modal();
                        $.ajax({
                            headers: {
                                'X-API-KEY': $('meta[name="api-key"]').attr('content')
                            },
                            url: '{{ Config::get('app.url') }}/api/flights/' + flight_id +
                                '/aircraft'
                        }).then((response) => {
                            aircrafts = [];
                            const results = response.map(ac => {
                                const text =
                                    `[${ac.icao}] ${ac.registration} ${ac.registration !== ac.name ? ` ${ac.name}` : ''}`;

                                aircrafts.push({
                                    id: ac.id,
                                    text: text
                                })
                            });
                            $('#aircraft_select option').remove();
                            sel.select2({
                                dropdownParent: $('#bidModal'),
                                data: aircrafts
                            });
                        });
                    } else {
                        phpvms.bids.removeBid(flight_id).then(() => {
                            console.log('successfully removed flight');
                            btn.removeClass(class_name);
                            alert('@lang('flights.bidremoved')');
                        }).catch((error) => {
                            if (error.response && error.response.data)
                                alert(`Error removing bid: ${error.response.data.details}`)
                            else alert(`Error removing bid: ${error.message}`)
                        });
                    }
                });


                $('#with_aircraft').click(() => {
                    const ac_id = $('#aircraft_select').val()
                    const flight_id = $('#aircraft_select').attr('x-id');
                    const class_name = $('#aircraft_select').attr('x-saved-class')
                    phpvms.bids.addBid(flight_id, ac_id).then(() => {
                        console.log('successfully saved flight');
                        $('button.save_flight[x-id="' + flight_id + '"]').addClass(class_name);
                        alert('@lang('flights.bidadded')');
                    }).catch((error) => {
                        if (error.response && error.response.data)
                            alert(`Error adding bid: ${error.response.data.details}`)
                        else alert(`Error adding bid: ${error.message}`)
                    });

                });

                $('#without_aircraft').click(async () => {
                    const flight_id = $('#aircraft_select').attr('x-id');
                    const class_name = $('#aircraft_select').attr('x-saved-class')

                    phpvms.bids.addBid(flight_id).then(() => {
                        console.log('successfully saved flight');
                        $('button.save_flight[x-id="' + flight_id + '"]').addClass(class_name);
                        alert('@lang('flights.bidadded')');
                    }).catch((error) => {
                        if (error.response && error.response.data) alert(
                            `Error adding bid: ${error.response.data.details}`)
                        else alert(`Error adding bid: ${error.message}`)
                    });
                });
            });
        </script>
    @else
        <script>
            $(document).ready(function() {
                $("button.save_flight").click(function(e) {
                    e.preventDefault();

                    const btn = $(this);
                    const class_name = btn.attr('x-saved-class'); // classname to use is set on the element
                    const flight_id = btn.attr('x-id');

                    if (!btn.hasClass(class_name)) {
                        phpvms.bids.addBid(flight_id).then(() => {
                            console.log('successfully saved flight');
                            btn.addClass(class_name)
                            alert('@lang('flights.bidadded')');
                        }).catch((error) => {
                            if (error.response && error.response.data) 
                            alert(`Error adding bid: ${error.response.data.details}`)
                            else alert(`Error adding bid: ${error.message}`)
                        });
                    } else {
                        phpvms.bids.removeBid(flight_id).then(() => {
                            console.log('successfully removed flight');
                            btn.removeClass(class_name);
                            alert('@lang('flights.bidremoved')');
                        }).catch((error) => {
                            if (error.response && error.response.data)
                                alert(`Error removing bid: ${error.response.data.details}`)
                            else alert(`Error removing bid: ${error.message}`)
                        });
                    }
                });
            });
        </script>
    @endif

    @include('scripts.airport_search')
@endsection
