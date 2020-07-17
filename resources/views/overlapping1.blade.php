<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Tornado API - Overlapping</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">

</head>

<body class="container">
  <div class="row">
    <div class="col-6">
      <form action="/overlapping1">
        <div class="form-group">
          <label for="polygons">Ranges (ex: distance,min,max,event)</label>
          <textarea name="ranges" class="form-control" cols="5" rows="15">{{$response['ranges']}}</textarea>
        </div>
        <button class="btn btn-success">Search</button>
      </form>
    </div>
    <div class="col-6">
      <table class="table">
        <thead>
          <tr>
            <th scope="col">Distance</th>
            <th scope="col">Ranges</th>
            <th scope="col">Event</th>
          </tr>
        </thead>
        <tbody>
          @foreach($response['results'] as $result)
          <tr>
            <td>{{$result['distance']}}</td>
            <td>{{$result['range']}}</td>
            <td>{{$result['event']}}</td>            
          </tr>
          @endforeach          
        </tbody>
      </table>

    </div>
  </div>
  <div class="row">

  </div>
</body>

</html>