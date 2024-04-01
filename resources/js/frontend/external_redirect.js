export default () => {
  document.addEventListener('DOMContentLoaded', () => {
    const links = document.querySelectorAll('a[data-external-redirect]');

    links.forEach((link) => {
      const href = link.getAttribute('href');

      // Check if the link is external
      link.addEventListener('click', (e) => {
        let externalHost;
        if (link.getAttribute('data-external-redirect') !== 'true') {
          externalHost = new URL(link.getAttribute('data-external-redirect')).hostname;
        } else {
          externalHost = new URL(href).hostname;
        }

        // We check if the user has already trusted the domain
        const trustedDomains = window.localStorage.getItem('trustedDomains') ? JSON.parse(window.localStorage.getItem('trustedDomains')) : [];
        if (trustedDomains.includes(externalHost)) {
          return;
        }

        // We prevent the link from opening
        e.preventDefault();

        $('#externalRedirectHost').html(externalHost);
        $('#externalRedirectUrl').attr('href', href);
        $('#externalRedirectModal').modal('show');
      });
    });

    document.querySelector('#externalRedirectUrl')
      .addEventListener('click', () => {
        if (document.querySelector('#redirectAlwaysTrustThisDomain').checked) {
          const host = new URL(document.querySelector('#externalRedirectUrl').getAttribute('href')).hostname;
          const trustedDomains = window.localStorage.getItem('trustedDomains') ? JSON.parse(window.localStorage.getItem('trustedDomains')) : [];

          if (!trustedDomains.includes(host)) {
            trustedDomains.push(host);
            window.localStorage.setItem('trustedDomains', JSON.stringify(trustedDomains));
          }
        }

        $('#externalRedirectModal')
          .modal('hide');
      });
  });
};
