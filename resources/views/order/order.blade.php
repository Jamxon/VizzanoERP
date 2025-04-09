<!DOCTYPE html>
<html>
<head>
    <title>Order #{{ $order->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid black; padding: 8px; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
<h1>Order: {{ $order->name }}</h1>
<p><strong>ID:</strong> {{ $order->id }}</p>
<p><strong>Status:</strong> {{ $order->status }}</p>
<p><strong>Quantity:</strong> {{ $order->quantity }}</p>
<p><strong>Start:</strong> {{ $order->start_date }}</p>
<p><strong>End:</strong> {{ $order->end_date }}</p>
<p><strong>Comment:</strong> {{ $order->comment }}</p>

<h2>Model:</h2>
<p><strong>Model Name:</strong> {{ $order->order_model['model']['name'] ?? '-' }}</p>
<p><strong>Material:</strong> {{ $order->order_model['material']['name'] ?? '-' }}</p>

<h3>Sizes:</h3>
<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Size</th>
        <th>Quantity</th>
    </tr>
    </thead>
    <tbody>
    @foreach($order->order_model['sizes'] as $size)
        <tr>
            <td>{{ $size['id'] }}</td>
            <td>{{ $size['size']['name'] ?? '-' }}</td>
            <td>{{ $size['quantity'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<h3>Instructions:</h3>
<ul>
    @foreach($order->instructions as $instruction)
        <li><strong>{{ $instruction['title'] }}:</strong> {{ $instruction['description'] }}</li>
    @endforeach
</ul>

<h3>Submodels:</h3>
@foreach($order->order_model['submodels'] as $submodel)
    <p>
        <strong>{{ $submodel['submodel']['name'] ?? '-' }}</strong><br>
        Spends: {{ $submodel['spends'] ?? '-' }}
    </p>
@endforeach

</body>
</html>
