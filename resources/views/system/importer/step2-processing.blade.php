@extends('system.importer.app')
@section('title', 'Import Configuration')

@section('content')
  <div style="align-content: center;">
    <form method="post" action="{{ route('importer.complete') }}">
      @csrf
    <table class="table" width="25%">

      <tr>
        <td colspan="2"><h4>Running Importer</h4></td>
      </tr>

      <tr>
        <td colspan="2">
          <div class="progress">
            <div id="progress" class="progress-bar" style="width: 0%"></div>
          </div>
          <div>
            <p id="message" style="margin-top: 7px;"></p>
            <p id="error" class="text-danger" style="margin-top: 7px;"></p>
          </div>
        </td>
      </tr>

    </table>
    <p style="text-align: right">
      <button type="submit" class="btn btn-success" id="completebutton">Complete Import</button>
    </p>
    </form>
  </div>
@endsection

@section('scripts')
  <script>
    const manifest = {!!json_encode($manifest) !!};

    /**
     * Run each step of the importer
     */
    async function startImporter() {
      let current = 1;
      const total_steps = manifest.length;

      /**
       * Update the progress bar
       */
      const setProgress = (current, message) => {
        const percent = Math.round(current / total_steps * 100);
        $("#progress").css("width", `${percent}%`);
        $("#message").text(message);
      };

      /**
       * Sleep for a given interval
       */
      const sleep = (timeout) => {
        return new Promise((resolve, reject) => {
          setTimeout(() => {
            resolve();
          }, timeout);
        });
      };

      const setError = (error) => {
        let message = '';
        if (error.response) {
          // The request was made and the server responded with a status code
          // that falls out of the range of 2xx
          console.log(error.response.data);
          console.log(error.response.status);
          console.log(error.response.headers);

          message = error.response.data.message;

        } else if (error.request) {
          // The request was made but no response was received
          // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
          // http.ClientRequest in node.js
          console.log(error.request);
          message = error.request;
        } else {
          // Something happened in setting up the request that triggered an Error
          console.log('Error', error.message);
          message = error.message;
        }

        $("#error").text(`Error processing, check the logs: ${message}`);
        console.log(error.config);
      };

      /**
       * Call the endpoint as a POST
       */
      const runStep = async function (stage) {
        setProgress(current, stage.message);

        try {
          return await phpvms.request({
            method: 'post',
            url: '/importer/run',
            data: {
              importer: stage.importer,
              start: stage.start,
            }
          });
        } catch (e) {

          if (e.response.status === 504) {
            const err = $("#error");

            console.log('got timeout, retrying');
            err.text(`Timed out, attempting to retry`);

            // await sleep(5000);
            const val = await runStep(stage);

            err.text('');

            return val;
          }

          setError(e);
          throw e;
        }
      };

      let errors = false;
      const complete_button = $("#completebutton");
      complete_button.hide();

      for (let stage of manifest) {
        console.log(`Running ${stage.importer} step ${stage.start}`);
        try {
          await runStep(stage);
        } catch (e) {
          errors = true;
          break;
        }

        current++;
      }

      if (!errors) {
        $("#message").text('Done!');
        complete_button.show();
      }
    }

    $(document).ready(() => {
      startImporter();
    });
  </script>
@endsection
