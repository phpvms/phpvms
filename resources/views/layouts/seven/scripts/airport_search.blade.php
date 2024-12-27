<script>

  // TODO: Change Search fields for better search experience.
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll("select.airport_search").forEach(function(element) {
      new TomSelect(element, {
        valueField: 'id',
        labelField: 'description',
        searchField: 'name',
        load: function(query, callback) {
          var url = new URL('{{ Config::get("app.url") }}/api/airports/search');
          var params = {
            search: query,
            hubs: element.classList.contains('hubs_only') ? 1 : 0,
            page: 1,
            orderBy: 'id',
            sortedBy: 'asc'
          };
          Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
          fetch(url)
            .then(response => response.json())
            .then(json => {
              console.log(json);
              callback(json.data);
            }).catch(()=>{
              callback();
            });
        }
      });
    });
  });
</script>
