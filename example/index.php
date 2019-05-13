<?php
/*
// add your Spark bearer token below, then
// from a console, cd to this directory and run
// php -S localhost:8000
// then visit http://localhost:8000/examples to view this sample page
*/

$bearer_token = '';

// ------------------------------ //

require_once ('../lib/Core.php');

// set up the spark API
$api = new SparkAPI_Bearer($bearer_token);
$api->SetApplicationName("Spark Sample Page");

// get agent info
$agent = $api->GetMyAccount();

// get listings
$listings = $api->GetListings(
    [
        '_filter' => "PropertyType Eq 'A'", // stick with residential
        '_expand' => "PrimaryPhoto", // grab the main photo for the listing
        '_limit' => 9 // keep this divisible by three for the layout
    ]
);

?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Spark Platform Example">
    <meta name="author" content="FBS">

    <title>Spark API Demo Page</title>

    <!-- Fontawesome CSS -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css" integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay" crossorigin="anonymous">
    <!-- Bootstrap core CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

    <style>
        :root {
            --jumbotron-padding-y: 3rem;
        }

        .jumbotron {
            padding-top: var(--jumbotron-padding-y);
            padding-bottom: var(--jumbotron-padding-y);
            margin-bottom: 0;
            background-color: #fff;
        }
        @media (min-width: 768px) {
            .jumbotron {
                padding-top: calc(var(--jumbotron-padding-y) * 2);
                padding-bottom: calc(var(--jumbotron-padding-y) * 2);
            }
        }

        .jumbotron p:last-child {
            margin-bottom: 0;
        }

        .jumbotron-heading {
            font-weight: 300;
        }

        .jumbotron .container {
            max-width: 40rem;
        }

        footer {
            padding-top: 3rem;
            padding-bottom: 3rem;
        }

        footer p {
            margin-bottom: .25rem;
        }

        .box-shadow { box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .05); }
    </style>
</head>

<body>

<header>
    <div class="collapse bg-dark" id="navbarHeader">
        <div class="container">
            <div class="row">
                <div class="col-sm-8 col-md-7 py-4">
                    <h4 class="text-white">Spark API Sample</h4>
                    <p class="text-muted">Sample Spark Platform Real Estate Listings</p>
                </div>
                <div class="col-sm-4 offset-md-1 py-4">
                    <h4 class="text-white"><?php echo $agent['Name']; ?></h4>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white">Follow on Twitter</a></li>
                        <li><a href="#" class="text-white">Like on Facebook</a></li>
                        <li><a href="#" class="text-white">Email me</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="navbar navbar-dark bg-dark box-shadow">
        <div class="container d-flex justify-content-between">
            <a href="#" class="navbar-brand d-flex align-items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                <strong>Spark API Demo</strong>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarHeader" aria-controls="navbarHeader" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </div>
</header>

<main role="main">

    <section class="jumbotron text-center">
        <div class="container">
            <h1 class="jumbotron-heading">Spark Platform for Real Estate</h1>
            <p class="lead text-muted">A quick demo of what you can do with the power of the Spark API!</p>
            <p>
                <a href="#" class="btn btn-primary my-2">Main call to action</a>
                <a href="#" class="btn btn-secondary my-2">Secondary action</a>
            </p>
        </div>
    </section>

    <div class="album py-5 bg-light">
        <div class="container">
            <?php
            $chunks = array_chunk($listings, 3);
            foreach ($chunks as $chunk) {
                echo "<div class='row'>";
                foreach ($chunk as $key => $listing) {

                    echo '
                    <div class="col-md-4">
                    <div class="card mb-4 box-shadow">
                        <img class="card-img-top" src="' . $listing['StandardFields']['Photos'][0]['Uri800'] . '" alt="' . $listing['StandardFields']['Photos'][0]['Name'] . '">
                        <div class="card-body">
                            <p class="card-text">' . $listing['StandardFields']['PublicRemarks'] . '
                            <br />Year Built: ' . $listing['StandardFields']['YearBuilt'] . '
                            <br /><i class="fas fa-bed"> ' . $listing['StandardFields']['BedsTotal'] . '</i>
                            <i class="fas fa-bath"> ' . $listing['StandardFields']['BathsTotal'] . '</i>
                            <i class="fas fa-chart-area"> ' . $listing['StandardFields']['BuildingAreaTotal'] . '</i>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary">View</button>
                                </div>
                                <small class="text-muted">Last update: ' . date('m-d-Y', strtotime($listing['StandardFields']['ModificationTimestamp'])) . '</small>
                            </div>
                        </div>
                    </div>
                    </div>';
                }
                echo '</div>';
            }
            ?>
        </div>
    </div>

</main>

<footer class="text-muted">
    <div class="container">
        <p class="float-right">
            <a href="#">Back to top</a>
        </p>
        <p>Album example is &copy; Bootstrap, but it's been Spark-ified for Flexmls!</p>
        <p>New to Spark Platform? <a href="https://www.sparkplatform.com">Visit our information site</a> or read our <a href="http://sparkplatform.com/docs">docs</a>.</p>
    </div>
</footer>

<!-- Bootstrap core JavaScript
================================================== -->
<!-- Placed at the end of the document so the pages load faster -->
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.15.0/popper.min.js" integrity="sha256-1XfFQxRfNvDJW3FdZ+xlo2SbodG2+rFArw6XsVzu3bc=" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/holder/2.9.6/holder.min.js" integrity="sha256-yF/YjmNnXHBdym5nuQyBNU62sCUN9Hx5awMkApzhZR0=" crossorigin="anonymous"></script>
</body>
</html>
