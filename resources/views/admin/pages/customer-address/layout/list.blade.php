@foreach($customer_addresses as $customer_address)
    <div
        class="col-lg-offset-1 col-lg-10 col-md-offset-0 col-md-12 col-sm-offset-0 col-sm-12 col-xs-offset-0 col-xs-12 list-row roles">
        <div class="col-lg-1 col-md-2 col-sm-3 col-xs-3 col">
            <div class="img-container">
                <img class="img-responsive" src="/admin_dashboard/images/No_image.jpg.png">
            </div>
        </div>
        <div class="col-lg-1 col-md-2 col-sm-2 col-xs-3 col">
            <div class="label">شناسه</div>
            <div>{{$customer_address->id}}#</div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-3 col-xs-6 col">
            <div class="label">نام خریدار</div>
            <div>{{$customer_address->customer->user->full_name}}</div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-4 col-xs-12 col">
            <div class="label">نام آدرس</div>
            <div>{{$customer_address->name}}</div>
        </div>
        <div class="col-lg-4 col-md-8 col-sm-8 col-xs-12 col">
            <div class="label">آدرس</div>
            <div>{{$customer_address->getFullAddress()}}</div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-4 col-xs-12 col">
            <div class="label">عملیات</div>
            <div class="actions-container">
                <a class="btn btn-sm btn-primary" href="{{route('admin.customer-address.edit', $customer_address)}}">
                    <i class="fa fa-pencil"></i>
                </a>
                <a class="btn btn-sm btn-danger virt-form"
                   data-action="{{ route('admin.customer-address.destroy', $customer_address) }}"
                   data-method="DELETE" confirm>
                    <i class="fa fa-trash"></i>
                </a>
                <a class="btn btn-sm btn-success" href="{{route('admin.customer-address.show', $customer_address)}}">
                    <i class="fa fa-eye"></i>
                </a>
            </div>
        </div>
    </div>
@endforeach
