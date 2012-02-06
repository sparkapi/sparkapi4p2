flexmls API - version 2
=====================
A PHP wrapper for the flexmls REST API.  This version has enough differences from version 1 that upgrading will
require changes to existing code.


Documentation
-------------
For full information on the API, see  http://www.flexmls.com/developers


Usage Examples
------------------------
    // include the flexmlsAPI core which autoloads other classes as necessary
    require_once("lib/Core.php");

    // connect using flexmls API authentication
    $api = new flexmlsAPI_APIAuth("api_key_goes_here", "api_secret_goes_here");

    // identify your application (optional)
    $api->SetApplicationName("MyPHPApplication/1.0");

    // authenticate
    $result = $api->Authenticate();
    if ($result === false) {
        echo "API Error Code: {$api->last_error_code}<br>\n";
        echo "API Error Message: {$api->last_error_mess}<br>\n";
        exit;
    }

    // get your listings
    $result = $api->GetMyListings();

    // see the included examples.php for more complete usage


Error Codes
---------------------
<table>
  <thead>
    <tr>
      <th>HTTP Code</th>
      <th>flexmls API Error Code</th>
      <th>Automatic Retry</th>
      <th>Description</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><tt>401</tt></td>
      <td><tt>1000</tt></td>
      <td><tt>Yes</tt></td>
      <td>Invalid API Key and/or Request signed improperly</td>
    </tr>
    <tr>
      <td><tt>401</tt></td>
      <td><tt>1010</tt></td>
      <td><tt>No</tt></td>
      <td>API key is disabled</td>
    </tr>
    <tr>
      <td><tt>403</tt></td>
      <td><tt>1015</tt></td>
      <td><tt>No</tt></td>
      <td><tt>ApiUser</tt> must be supplied, or the provided key does not have access to the supplied user</td>
    </tr>
    <tr>
      <td><tt>401</tt></td>
      <td><tt>1020</tt></td>
      <td><tt>Yes</tt></td>
      <td>Session token has expired</td>
    </tr>
    <tr>
      <td><tt>403</tt></td>
      <td><tt>1030</tt></td>
      <td><tt>No</tt></td>
      <td>SSL required for this type of request</td>
    </tr>
    <tr>
      <td><tt>400</tt></td>
      <td><tt>1035</tt></td>
      <td><tt>No</tt></td>
      <td>POST data not supplied as valid JSON. Issued if the <tt>Content-Type</tt> header is not <tt>application/json/</tt> and/or if the POST data is not in valid JSON format.</td>
    </tr>
    <tr>
      <td><tt>400</tt></td>
      <td><tt>1040</tt></td>
      <td><tt>No</tt></td>
      <td>The <tt>_filter</tt> syntax was invalid or a specified field to search on does not exist</td>
    </tr>
    <tr>
      <td><tt>400</tt></td>
      <td><tt>1050</tt></td>
      <td><tt>No</tt></td>
      <td>(message varies) A required parameter was not provided</td>
    </tr>
    <tr>
      <td><tt>400</tt></td>
      <td><tt>1053</tt></td>
      <td><tt>No</tt></td>
      <td>(message varies) A parameter was provided but does not adhere to constraints</td>
    </tr>
    <tr>
      <td><tt>409</tt></td>
      <td><tt>1055</tt></td>
      <td><tt>No</tt></td>
      <td>(message varies)Issued when a write is requested that will conflict with existing data. For example, adding a new contact with an e-mail that already exists.</td>
    </tr>
    <tr>
      <td><tt>403</tt></td>
      <td><tt>1500</tt></td>
      <td><tt>No</tt></td>
      <td>The resource is not available at the current API key's service level. For example, this error applies if a user attempts to access the IDX Links API via a free API key. </td>
    </tr>
    <tr>
      <td><tt>503</tt></td>
      <td><tt>1550</tt></td>
      <td><tt>No</tt></td>
      <td>Over rate limit</td>
  </tbody>
</table>


