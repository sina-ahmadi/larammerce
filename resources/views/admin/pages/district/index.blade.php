@extends('admin.layout')

@section('bread_crumb')
    <li><a href="{{route('admin.district.index')}}">منطقه ها</a></li>
    <li class="active"><a href="{{route('admin.district.index')}}">لیست منطقه ها</a></li>

@endsection

@section('main_content')
    <div class="inner-container">
        <div class="toolbar">
            <ul class="has-divider-left">
                <li class="btn btn-default" href="{{route('admin.state.index')}}" act="link">
                    <i class="fa fa-globe"></i>استان ها
                </li>
                <li class="btn btn-default" href="{{route('admin.city.index')}}" act="link">
                    <i class="fa fa-map-o"></i>شهر ها
                </li>
            </ul>
            <ul class="has-divider-left">
                @foreach(LayoutService::getLayoutMethods() as $layout_method)
                    <li href="{{route('admin.null')}}?layout_model=District&layout_method={{$layout_method["method"]}}"
                        act="link"
                        @if($layout_method["method"] == LayoutService::getRecord("District")->getMethod()) class="active" @endif>
                        <i class="fa {{$layout_method["icon"]}}"></i>
                    </li>
                @endforeach
            </ul>
            <ul>
                @foreach(SortService::getSortableFields('District') as $sortable_field)
                    <li class="btn btn-default {{$sortable_field->is_active ? "active" : ""}}"
                        href="{{route('admin.null')}}?sort_model=District&sort_field={{$sortable_field->field}}&sort_method={{$sortable_field->method}}"
                        act="link">
                        @if($sortable_field->is_active)
                            <i class="fa {{$sortable_field->method == SortMethod::ASCENDING ? "fa-long-arrow-up" : "fa-long-arrow-down"}}"></i>
                        @endif
                        {{$sortable_field->title}}
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="inner-container has-toolbar has-pagination">
            <div class="view-port">
                @include('admin.pages.district.layout.'.LayoutService::getRecord("District")->getMethod())
            </div>
            @if(isset($city))
                <div class="fab-container">
                    <div class="fab green">
                        <button act="link" href="{{route('admin.district.create')}}?city_id={{$city->id}}">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
            @endif
        </div>
        @include('admin.templates.pagination', [
            "modelName" => "District",
            "lastPage" => $districts->lastPage(),
            "total" => $districts->total(),
            "count" => $districts->perPage(),
            "parentId" => (isset($city) ? $city->id : $scope ?? null)
        ])
    </div>
@endsection
-
